# CLAUDE.md

## Project

AI Email Assistant

## Purpose

Internal Laravel application that integrates with GoHighLevel
Conversations API and an AI provider to generate email reply drafts.

## Core Principles

-   Simplicity first.
-   Build only MVP features.
-   Never over-engineer.
-   Do not implement features outside the PRD.

## MVP Features

-   Login
-   Inbox
-   Conversation List
-   Conversation Detail
-   Generate AI Draft
-   Regenerate Draft
-   Send Reply

## Tech Stack

-   Laravel 13
-   Blade + Bootstrap 5
-   MySQL
-   Laravel HTTP Client
-   GoHighLevel Private Integration API
-   OpenAI (replaceable)

## Architecture

-   Thin Controllers.
-   Business logic in Services.
-   Use dependency injection.
-   Keep integrations inside dedicated services.

Recommended services: - ConversationService - GoHighLevelService -
OpenAIService - PromptService - DraftService

## Database

Tables: - users - conversations - messages - drafts

## Conversation Rules

-   Always process a conversation thread, never only the latest email.
-   Preserve chronological order.
-   Preserve sender information.
-   Maintain context.

## AI Rules

-   Include conversation history.
-   Include company SOP.
-   Never hallucinate.
-   Never promise refunds.
-   Reply professionally.
-   Reply in the customer's language whenever possible.

## Coding Standards

-   Follow Laravel conventions.
-   Use Form Requests.
-   Use Eloquent relationships.
-   Prefer readability.
-   Avoid duplicated code.
-   Use typed properties and return types.

## Security

-   Store secrets in `.env`.
-   Validate all requests.
-   Never expose API keys.

## Performance

-   Paginate conversations.
-   Queue AI generation when appropriate.
-   Cache static configuration.

## Testing

-   Feature tests for user flows.
-   Unit tests for services.
-   Mock GoHighLevel and AI APIs.

## Final Rule

Always read `PRD.md` before implementing any feature.
