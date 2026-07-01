# Domain

## Purpose

Peix Scanner transforms fishery data into consumer-friendly knowledge.

The platform enriches technical information with AI to promote local seafood, sustainability and healthier food choices.

---

# Core Domain

The core business of Peix Scanner is **Species Intelligence**.

Everything else exists to support it.

```
Fishery Data
        │
        ▼
Species Identification
        │
        ▼
Semantic Enrichment
        │
        ▼
Consumer Recommendation
```

---

# Bounded Contexts

The application is divided into the following domains.

```
Species

Traceability

Nutrition

Recommendation

AI

Consumer
```

---

# Domain Overview

```
Species
    │
    ├──────────────┐
    │              │
    ▼              ▼

Traceability   Nutrition

    │              │
    └──────┬───────┘
           ▼

Recommendation

           │
           ▼

Consumer
```

---

# Species

Represents a fish species.

Responsible for:

- Scientific information
- Commercial information
- Species metadata

Examples

- Atlantic Horse Mackerel
- Blue Whiting
- Bogue
- Mackerel

---

# Traceability

Represents where the species comes from.

Includes

- Fishing area
- Capture date
- Fishing method
- Fish market
- Fishery

---

# Nutrition

Represents nutritional knowledge.

Examples

- Protein
- Omega 3
- Vitamins
- Minerals
- Calories

---

# Recommendation

Represents AI-generated consumer insights.

Examples

- Health benefits
- Sustainability
- Consumption advice
- Local alternatives

---

# AI

Responsible for enriching existing data.

The AI never creates business rules.

Its responsibility is:

- Explain
- Summarize
- Recommend

Never:

- Calculate
- Validate
- Decide

---

# Consumer

Represents the final user.

The application currently targets:

- Young adults
- Health-conscious users
- Amateur athletes
- Sustainable consumers

---

# Ubiquitous Language

| Business Term | Definition |
|---------------|------------|
| Species | A seafood species available for consumption |
| Traceability | Product origin and capture information |
| Recommendation | Consumer-friendly AI insight |
| Nutrition | Nutritional information |
| Sustainability | Environmental impact indicators |
| Species Insight | Complete enriched information generated for one species |

---

# Main Use Cases

UC-001

Identify a species from an image.

---

UC-002

Retrieve traceability information.

---

UC-003

Generate nutritional insights.

---

UC-004

Generate sustainability indicators.

---

UC-005

Generate AI recommendation.

---

UC-006

Display consumer-friendly product information.

---

# Business Rules

BR-001

Every recommendation belongs to one species.

---

BR-002

A recommendation cannot exist without a species.

---

BR-003

Every species may have nutritional information.

---

BR-004

Every species may have traceability information.

---

BR-005

AI never modifies stored data.

It only enriches information.

---

BR-006

Species identification must always include a confidence score.

---

BR-007

The user must confirm the detected species before generating recommendations.

---

# Out of Scope

The following features are intentionally excluded.

- Fish stock prediction
- Fishing recommendations
- Commercial transactions
- Marketplace
- Social network
- Fish auction management