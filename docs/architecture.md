# Architecture

## Overview

Peix Scanner follows a lightweight Domain-Driven Design (DDD) architecture with a clear separation between business rules and framework-specific code.

The goal is to keep the Domain independent from Laravel, allowing the application to evolve without coupling business logic to the framework.

---

# Principles

The architecture follows these principles.

- Keep business logic inside the Domain.
- Controllers coordinate, they never contain business rules.
- Eloquent models are persistence models, not business models.
- Every use case is represented by an Action.
- External services are isolated behind adapters.
- Framework code stays at the application's edges.
- Simplicity over unnecessary abstraction.

---

# Architecture Layers

```

┌──────────────────────────────┐
│ HTTP                         │
│ Controllers                  │
│ Middleware                   │
│ Requests                     │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ Application                  │
│ Actions                      │
│ DTOs                         │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ Domain                       │
│ Entities                     │
│ Services                     │
│ Policies                     │
│ Value Objects                │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ Infrastructure               │
│ Repositories                 │
│ AI                           │
│ External APIs                │
│ Storage                      │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ PostgreSQL                   │
└──────────────────────────────┘

```

---

## Folder Structure

```text

app/

Application/
Domain/
Infrastructure/
Shared/
Http/

```

---

# Responsibilities

## Http

Responsible for receiving HTTP requests.

Contains:

- Controllers
- Form Requests
- Middleware

Never contains business logic.

---

## Application

Responsible for executing use cases.

Contains:

- Actions
- DTOs

Coordinates the Domain.

---

## Domain

Contains the business knowledge.

Contains:

- Entities
- Services
- Policies
- Value Objects

Must not depend on Laravel.

---

## Infrastructure

Responsible for external integrations.

Examples:

- AI Providers
- Database
- Storage
- Email
- External APIs

---

## Shared

Reusable components.

Examples:

- Exceptions
- Traits
- Helpers
- Contracts

---

# Dependency Rule

Dependencies always point inward.

```

HTTP

↓

Application

↓

Domain

↑

Infrastructure

```

The Domain never depends on Infrastructure.

---

# Use Case Flow

```

HTTP Request

↓

Controller

↓

Action

↓

Domain Service

↓

Repository

↓

Database

↓

Response

```

---

# AI Integration

Artificial Intelligence is treated as an Infrastructure component.

Business rules never depend directly on an LLM.

```

Controller

↓

GenerateSpeciesInsightsAction

↓

SemanticAnalysisService

↓

AI Provider

↓

Response Parser

↓

DTO

↓

Domain

```

---

# Design Goals

- Readability
- Testability
- Maintainability
- Framework independence
- Small classes
- Explicit responsibilities

---

# Non Goals

This project intentionally avoids:

- Microservices
- CQRS
- Event Sourcing
- Hexagonal Architecture (full implementation)
- Premature optimization
- Overengineering

Those decisions may evolve as the product grows.
