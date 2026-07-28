<?php

declare(strict_types=1);

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\DTOs\IdentificationResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('saves the uploaded image to the private scan-uploads directory and returns an absolute path', function () {
    Storage::fake('local');
    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (string $path) {
                expect($path)->toBeString();
                expect(file_exists($path))->toBeTrue("file should exist at {$path}");

                return new IdentificationResult(
                    scientificName: 'salmo salar',
                    commonName: 'Atlantic salmon',
                    confidence: 0.9,
                );
            });
    });

    $fakeFile = UploadedFile::fake()->image('fillet.jpg');

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    $response->assertRedirect();
    Storage::disk('local')->assertExists('scan-uploads');
});
