# Database

## Philosophy

The database stores facts.

Business rules belong to the Domain.

AI-generated content is never considered the source of truth.

---

# Naming Convention

- Singular Models
- Plural Tables
- snake_case columns
- UUID primary keys
- Foreign keys always use *_id
- Timestamps enabled by default

---

# Initial Tables

users

species

species_images

traceability_records

nutrition_profiles

recommendations

ai_generations

---

# Entity Relationship

Users

↓

Recommendations

↓

Species

↓

Traceability

↓

Nutrition

↓

AI Generation

---

# Tables

## users

Purpose

Registered application users.

---

## species

Purpose

Stores fish species information.

Columns

- id
- common_name
- scientific_name
- description
- created_at
- updated_at

---

## species_images

Purpose

Stores uploaded species images.

Columns

- id
- species_id
- path
- mime_type
- hash
- created_at

---

## traceability_records

Purpose

Stores product origin.

Columns

- id
- species_id
- fishing_area
- capture_method
- capture_date
- fish_market
- created_at

---

## nutrition_profiles

Purpose

Stores nutritional values.

Columns

- id
- species_id
- calories
- protein
- omega3
- fat
- vitamins
- created_at

---

## recommendations

Purpose

Stores consumer recommendations.

Columns

- id
- species_id
- user_id
- recommendation
- language
- created_at

---

## ai_generations

Purpose

Stores AI responses.

Columns

- id
- recommendation_id
- provider
- model
- prompt_hash
- response
- execution_time
- created_at

---

# Relationships

Species

1 -> N Images

Species

1 -> 1 Nutrition

Species

1 -> N Traceability Records

Species

1 -> N Recommendations

Recommendation

1 -> 1 AI Generation

User

1 -> N Recommendations

---

# Future Tables

favorite_species

species_categories

markets

fisheries

scan_history

analytics

---

# Out of Scope

Shopping

Payments

Orders

Inventory

Notifications