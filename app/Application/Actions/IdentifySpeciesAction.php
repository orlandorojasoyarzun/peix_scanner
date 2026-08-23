<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Ai\AllowedSpecies;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use Illuminate\Support\Facades\Log;

class IdentifySpeciesAction
{
    public function __construct(
        private readonly SpeciesIdentifier $identifier,
    ) {}

    /**
     * Run the model once, validate the result against the species allowlist,
     * and return a canonical IdentificationResult.
     *
     * If every candidate the model returned is off-list, this throws
     * IdentificationFailedException with REASON_PARSE_FAILED — the controller
     * treats that as "especie no reconocida" and shows a friendly error.
     */
    public function execute(string $imagePath): IdentificationResult
    {
        $raw = $this->identifier->identify($imagePath);

        return $this->enforceAllowlist($raw);
    }

    public function identifyBatch(string $imagePath, int $attempts = 3): IdentificationResult
    {
        $votes = [];
        $bestResult = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $this->enforceAllowlist($this->identifier->identify($imagePath));
            } catch (IdentificationFailedException) {
                continue;
            }

            $key = $result->scientificName;

            if (! isset($votes[$key])) {
                $votes[$key] = ['count' => 0, 'result' => $result];
            }

            $votes[$key]['count']++;
            $votes[$key]['result'] = $result;

            if ($bestResult === null || $result->confidence > $bestResult->confidence) {
                $bestResult = $result;
            }
        }

        if ($bestResult === null) {
            // Fall back to a single-attempt result so the user gets *some*
            // answer; the allowlist guard will still reject off-list output.
            $fallback = $this->identifier->identify($imagePath);

            return $this->enforceAllowlist($fallback);
        }

        arsort($votes);

        $topKey = array_key_first($votes);

        return $votes[$topKey]['result'];
    }

    /**
     * Filter an IdentificationResult through the species allowlist.
     *
     * Behaviour:
     *   - Each candidate's scientific_name is normalised and checked.
     *   - Candidates not on the list are dropped (with a log warning if
     *     any were dropped — that's a signal of prompt injection or model
     *     drift).
     *   - The top candidate's common_name and common_name_local are
     *     replaced with the canonical values from the allowlist, so even
     *     a misnamed AI output renders as the canonical species.
     *   - If no candidate survives, throw PARSE_FAILED so the controller
     *     can show "no reconocemos esta especie" instead of caching garbage.
     */
    private function enforceAllowlist(IdentificationResult $raw): IdentificationResult
    {
        $kept = [];
        $dropped = [];

        foreach ($raw->candidates as $candidate) {
            $sci = AllowedSpecies::normaliseScientificName($candidate['scientific_name'] ?? '');

            if (AllowedSpecies::isAllowed($sci)) {
                $candidate['scientific_name'] = $sci;
                $kept[] = $candidate;
            } else {
                $dropped[] = $sci;
            }
        }

        if ($dropped !== []) {
            Log::warning('AI returned off-list candidates', [
                'kept_count' => count($kept),
                'dropped' => $dropped,
            ]);
        }

        if ($kept === []) {
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_PARSE_FAILED,
                ['reason_detail' => 'all_candidates_off_list'],
            );
        }

        // Rebuild the top result with canonical names.
        $top = $kept[0];
        $canonicalSci = AllowedSpecies::normaliseScientificName($top['scientific_name']);
        $top['scientific_name'] = $canonicalSci;
        $top['common_name'] = AllowedSpecies::commonEn($canonicalSci) ?? AllowedSpecies::sanitise($top['common_name'] ?? '');
        $top['common_name_local'] = AllowedSpecies::commonEs($canonicalSci) ?? AllowedSpecies::sanitise($top['common_name_local'] ?? '');
        $top['regional_names'] = array_values(array_filter(
            array_map([AllowedSpecies::class, 'sanitise'], $top['regional_names'] ?? []),
            static fn (string $v): bool => $v !== '',
        ));

        return new IdentificationResult(
            scientificName: $top['scientific_name'],
            commonName: $top['common_name'],
            confidence: (float) $top['confidence'],
            commonNameLocal: $top['common_name_local'],
            candidates: $kept,
            regionalNames: $top['regional_names'],
        );
    }
}
