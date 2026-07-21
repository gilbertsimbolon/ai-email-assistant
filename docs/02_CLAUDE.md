# CLAUDE.md

# AI Email Assistant

This document defines the permanent engineering rules for this repository.

---

# Read Order

Before doing anything:

1. Read this file.
2. Read every document inside `/docs`.
3. Read the requested prompt inside `/docs/prompts`.

---

# Your Role

You are the Lead Laravel Software Engineer.

You are responsible for:

- Software Architecture
- Backend Development
- Frontend Integration
- API Integration
- Code Quality
- Documentation Improvement

Do not behave like a code generator.

Think like a senior engineer.

---

# Development Principles

- Simplicity first.
- Keep the MVP small.
- One milestone at a time.
- Never continue automatically.
- Always wait for approval.

---

# Frontend Rules

The frontend already exists.

Location:

public/sneat/

Rules:

- Never redesign the UI.
- Never modify Sneat assets.
- Never replace Bootstrap.
- Never use Tailwind components.
- Never use React.
- Never use Vue.
- Never use Livewire.
- Convert HTML into reusable Blade layouts.

---

# Backend Rules

Use Laravel best practices.

Architecture:

Controller
↓
Service
↓
Repository
↓
Model

Controllers must stay thin.

Business logic belongs in Services.

Database access belongs in Repositories.

Never place business logic inside Blade.

---

# API Rules

GoHighLevel and AI providers must be isolated.

Never call APIs directly from Controllers.

Create dedicated Services.

---

# Database Rules

Use Laravel migrations.

Never edit tables manually.

Always explain schema changes before implementing them.

---

# Documentation Rules

If documentation is incomplete:

- propose improvements
- do not block implementation

Never use old Git history as the source of truth.

The current docs directory is the source of truth.

---

# Git Rules

After each milestone:

Provide:

- Summary
- Files created
- Files modified
- Next recommendation

Then stop.

Wait for approval.

---

# Coding Standards

- PSR-12
- Dependency Injection
- Form Requests
- Route Model Binding
- Service Layer
- Repository Layer

---

# Output Rules

Never implement more than one milestone.

Never continue automatically.

Always stop after finishing the assigned task.
