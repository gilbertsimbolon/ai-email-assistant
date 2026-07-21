# API_MAPPING.md

# AI Email Assistant

## Purpose

Defines all external APIs used by the application.

## External APIs

-   GoHighLevel Private Integration API
-   OpenAI API

## Base URL

https://services.leadconnectorhq.com

## Authentication

Headers: - Authorization: Bearer {GHL_TOKEN} - Version: 2021-07-28 -
Accept: application/json - Content-Type: application/json

Store token in `.env`.

## Required Endpoints

### 1. List Conversations

-   Method: GET
-   Endpoint: /conversations
-   Purpose: Display inbox conversations.

### 2. Get Conversation Messages

-   Method: GET
-   Endpoint: /conversations/{conversationId}/messages
-   Purpose: Retrieve the complete conversation thread.
-   Rule: Never generate AI replies using only the latest email.

### 3. Get Contact (Optional)

-   Method: GET
-   Endpoint: /contacts/{contactId}
-   Purpose: Retrieve customer information.

### 4. Send Reply

-   Method: POST
-   Endpoint: /conversations/messages
-   Purpose: Send email reply back to the same conversation.

## Internal Laravel Endpoints

  Method   Endpoint                       Purpose
  -------- ------------------------------ ---------------------
  GET      /                              Inbox
  GET      /conversations                 Conversation list
  GET      /conversations/{id}            Conversation detail
  POST     /conversations/{id}/generate   Generate AI draft
  POST     /conversations/{id}/send       Send reply

## Service Mapping

-   GoHighLevelService
-   ConversationService
-   PromptService
-   OpenAIService
-   DraftService

## AI Flow

Conversation List → Conversation Messages → Prompt Builder → OpenAI →
Draft → Review → Send Reply

## Error Handling

-   401 Unauthorized
-   403 Forbidden
-   404 Not Found
-   429 Rate Limited
-   500 Server Error

## Logging

Always log: - Request URL - Status Code - Execution Time - Conversation
ID

Never log: - API Tokens - Secrets

## Security

-   Store credentials in `.env`
-   Never expose API keys to the frontend.
-   Use HTTPS only.

## Future APIs (Not in MVP)

-   Calendars
-   Opportunities
-   Payments
-   Workflows
-   Funnels
-   Reputation
