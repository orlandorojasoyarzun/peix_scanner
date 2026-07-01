# Peix Scanner

> AI-powered platform that transforms fishery data into meaningful consumer insights.

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PHP](https://img.shields.io/badge/PHP-8.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Overview

Peix Scanner is a platform that helps consumers discover and understand local seafood.

By combining AI, traceability data and nutritional information, the application transforms technical fishery information into clear, accessible and actionable insights.

Our goal is to promote:

- Sustainable seafood consumption
- Local fisheries
- Species diversification
- Consumer awareness
- Product transparency

---

## Problem

Consumers usually buy the same fish species because they lack accessible information.

This creates:

- Overexploitation of popular species
- Low demand for local alternatives
- Price speculation
- Poor traceability visibility
- Limited consumer awareness

---

## Solution

Peix Scanner converts fishery data into consumer-friendly information.

Using AI, the platform identifies species and enriches them with:

- Traceability
- Nutritional information
- Sustainability indicators
- Health benefits
- Consumer recommendations

---

## Features

- Species identification
- AI-generated product insights
- Traceability information
- Nutritional analysis
- Sustainability score
- Consumer recommendations

---

## Tech Stack

| Layer | Technology |
|---------|------------|
| Backend | Laravel 12 |
| Frontend | Livewire + Volt |
| Styling | TailwindCSS |
| Database | PostgreSQL |
| Cache | Redis |
| AI | OpenAI / Ollama |
| Storage | Local / S3 |
| Testing | Pest |
| Static Analysis | PHPStan |
| Formatting | Laravel Pint |
| Containers | Docker |

---

## Architecture

The project follows a lightweight Domain-Driven Design approach.

```
Client

↓

HTTP

↓

Application

↓

Domain

↓

Infrastructure

↓

PostgreSQL
```

More information can be found inside the `docs/` directory.

---

## Getting Started

```bash
git clone https://github.com/<organization>/peix_scanner.git

cd peix_scanner

cp .env.example .env

composer install

php artisan key:generate

php artisan migrate

npm install

npm run dev

php artisan serve
```

---

## Development

### Code Style

```bash
./vendor/bin/pint
```

### Static Analysis

```bash
./vendor/bin/phpstan analyse
```

### Tests

```bash
php artisan test
```

---

## Roadmap

- [ ] Species identification
- [ ] AI recommendation engine
- [ ] Traceability module
- [ ] Nutritional insights
- [ ] Sustainability engine
- [ ] Consumer dashboard
- [ ] Public API

---

## Contributing

Pull Requests are welcome.

Please follow the project's coding standards and commit conventions.

---

## License

MIT