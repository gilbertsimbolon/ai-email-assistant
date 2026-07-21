# Product Requirements Document (PRD)

**Project Name:** AI Email Assistant  
**Document Owner:** Senior Product Manager & Solution Architect  
**Target Platform:** Laravel 13, Bootstrap 5, MySQL, OpenAI API, GoHighLevel Private API  
**Document Status:** Approved for Implementation  
**Intended Audience:** AI Coding Assistants (e.g., Claude Code), Engineering Team  

---

## 1. Executive Summary

The **AI Email Assistant** is an internal, highly specialized productivity web application built for customer support agents. The single goal of this system is to drastically reduce customer support response times by automatically generating contextual email replies using OpenAI GPT-4o whenever an inbound email arrives via GoHighLevel (GHL).

Agents log into a two-screen interface (Login and Inbox) where they review pre-generated email drafts, make optional manual edits, or click **Send**. Once confirmed, the system dispatches the email reply back to the customer via GoHighLevel's Conversations API.

> **Strict Project Guardrails:**
> * **NOT a CRM:** No contact profile management, deal pipelines, or sales tracking.
> * **NOT a Helpdesk:** No ticket assignments, SLA rules, tagging, internal notes, or queues.
> * **NOT a SaaS:** Single-tenant internal software built strictly for internal team use.
> * **Zero Manual Drafting:** Agents should never write emails from scratch unless edge-case modifications are necessary.

---

## 2. Business Problem & Primary Goal

### 2.1 Business Problem
Support agents spend significant time reading long conversation histories, context-switching, and typing manual responses to recurring customer inquiries. This creates high response latency, inconsistent brand tone, and operational bottlenecks.

### 2.2 Primary Goal
To achieve an **80% reduction in customer support response time** by presenting support agents with full-context, AI-generated email drafts ready for immediate review and single-click sending.

---

## 3. End-to-End System Workflow

```
[ Customer Email ]
       │
       ▼
[ GoHighLevel ] ──( Inbound Webhook )──> [ Laravel Webhook Endpoint ]
                                                   │
                                                   ▼
[ OpenAI API ] <──( Send Full Context ) ── [ Fetch Full Message Thread ]
      │                                            │
      ▼                                            │
[ Generate Draft ]                                 │
      │                                            │
      ▼                                            ▼
[ Store Draft (status: pending) ] ──> [ MySQL DB: conversations / messages / drafts ]
                                                   │
                                                   ▼
[ Agent Opens Laravel ] ──> [ Inbox Page: Selects Thread & Reviews Draft ]
                                                   │
                                         ┌─────────┴─────────┐
                                         ▼                   ▼
                                 ( Click Regenerate )  ( Click Send )
                                         │                   │
                                         ▼                   ▼
                                 [ New AI Draft ]    [ Dispatch via GHL API ]
                                                             │
                                                             ▼
                                                    [ Customer Email ]
```

---

## 4. Technical Stack Architecture

| Layer | Technology | Specifications / Configuration |
|---|---|---|
| **Backend Framework** | Laravel 13 | PHP 8.3+, Artisan CLI, Queue Worker with Redis/Database driver |
| **Frontend Framework** | Blade & Bootstrap 5 | Blade Templates, Bootstrap 5 CSS/JS, Vanilla JS (Fetch API) |
| **Database** | MySQL 8.0+ | InnoDB Engine, UTF8MB4 Collation |
| **AI Integration** | OpenAI API | Model: `gpt-4o` (configurable via `.env`) |
| **GHL Integration** | GoHighLevel Private Integration | API v2 / Conversations Endpoint, Bearer Token Auth |
| **Background Queues** | Laravel Queues | Job: `ProcessInboundEmailJob`, `SendGhlEmailJob` |

---

## 5. Database Schema & Data Models

The application strictly utilizes four database tables: `users`, `conversations`, `messages`, and `drafts`.

```sql
-- 1. Users Table
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Conversations Table
CREATE TABLE `conversations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ghl_conversation_id` VARCHAR(255) UNIQUE NOT NULL,
  `ghl_location_id` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(255) NULL,
  `contact_email` VARCHAR(255) NULL,
  `status` ENUM('pending_review', 'replied', 'failed') DEFAULT 'pending_review',
  `last_message_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_ghl_conversation` (`ghl_conversation_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Messages Table
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `ghl_message_id` VARCHAR(255) UNIQUE NOT NULL,
  `sender_type` ENUM('customer', 'agent', 'system') NOT NULL,
  `body` TEXT NOT NULL,
  `sent_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  INDEX `idx_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Drafts Table
CREATE TABLE `drafts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `draft_content` TEXT NOT NULL,
  `prompt_tokens` INT UNSIGNED DEFAULT 0,
  `completion_tokens` INT UNSIGNED DEFAULT 0,
  `status` ENUM('active', 'regenerated', 'sent', 'discarded') DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  INDEX `idx_conversation_draft` (`conversation_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Detailed Functional Requirements

### 6.1 Authentication & Login Page
* **FR-1.1:** Single authentication page utilizing Laravel's built-in session authentication (`web` guard).
* **FR-1.2:** Clean Bootstrap 5 card container centered on screen with `Email` and `Password` input fields and a `Login` button.
* **FR-1.3:** Unauthenticated requests to any path (e.g., `/inbox`) MUST automatically redirect to `/login`.
* **FR-1.4:** Successful authentication redirects the agent directly to `/inbox`.

### 6.2 Inbox Page Layout
The Inbox page is a two-column, high-density dashboard built using Bootstrap 5 grid:

#### Left Panel: Conversation List (30% width)
* **FR-2.1:** Displays list of conversation threads ordered by `last_message_at` DESC.
* **FR-2.2:** Filter tabs at the top: `Pending Review` (default) and `Replied`.
* **FR-2.3:** Each conversation item displays:
  * Customer Name (fallback to `contact_email` if name is missing).
  * Last message relative timestamp (e.g., "5m ago", "2h ago").
  * Message body preview snippet (truncated to 60 characters).
  * Visual badge indicator for pending draft status.

#### Right Panel: Conversation Detail & Draft Workspace (70% width)
* **FR-2.4 Header Area:** Shows customer name, email address, GHL Conversation ID, and status badge.
* **FR-2.5 Message History Thread:**
  * Displays entire conversation history in chronological order (oldest to newest).
  * Visual distinction: Customer messages aligned left (light background); Agent messages aligned right (blue background).
  * Renders full message body text cleanly formatted.
* **FR-2.6 AI Draft Editor Area:**
  * Displays the latest `active` draft generated by OpenAI inside an editable Bootstrap `<textarea>`.
  * Allows support agents to manually edit draft content directly prior to sending.
* **FR-2.7 Control Action Bar:**
  * **Regenerate Button:** Secondary Bootstrap button (`btn-outline-secondary`). Triggers asynchronous re-call to OpenAI API to replace the current draft.
  * **Send Button:** Primary Bootstrap button (`btn-success`). Dispatches the text in the draft textarea to GHL via API.

### 6.3 Webhook Ingestion & Automated AI Generation
* **FR-3.1 Webhook Endpoint:** `POST /api/v1/webhooks/ghl` receiving JSON payloads from GoHighLevel.
* **FR-3.2 Webhook Handshake:** Endpoint validates incoming payload and returns HTTP `200 OK` within 200ms by dispatching a background job: `ProcessInboundEmailJob`.
* **FR-3.3 Sync Message Context:** `ProcessInboundEmailJob` calls GHL API `GET /conversations/{conversationId}/messages` to fetch all historic messages for the thread.
* **FR-3.4 Trigger AI Generation:** Backend constructs a structured array of all messages in chronological order and dispatches request to OpenAI Chat Completions API (`gpt-4o`).
* **FR-3.5 Save Draft:** Generated text is saved in the `drafts` table with `status = 'active'`. The associated `conversations.status` is set to `pending_review`.

### 6.4 Regenerate Draft
* **FR-4.1:** Clicking "Regenerate" sends an AJAX POST request to `/conversations/{id}/regenerate`.
* **FR-4.2:** The backend updates the existing draft status to `regenerated`.
* **FR-4.3:** The backend calls OpenAI API with full conversation context and an added system temperature variance parameter to generate a fresh reply.
* **FR-4.4:** The new response is saved as the new `active` draft and returned via JSON to dynamically update the textarea without page refresh.

### 6.5 Send Reply
* **FR-5.1:** Clicking "Send" sends an AJAX POST request to `/conversations/{id}/send` containing the current text inside the draft textarea.
* **FR-5.2 Button Locking:** UI immediately disables the "Send" button and displays a spinner to prevent duplicate dispatches.
* **FR-5.3 GHL API Dispatch:** Backend sends a `POST` request to `https://services.leadconnectorhq.com/conversations/messages`.
* **FR-5.4 State Update:** Upon GHL API success response:
  * Draft record updated: `status = 'sent'`.
  * Conversation status updated: `status = 'replied'`.
  * A new record inserted into `messages` table (`sender_type = 'agent'`).
* **FR-5.5 UI Refresh:** UI displays a success toast notification and automatically loads the next conversation in `pending_review` status.

---

## 7. AI System Prompt & Processing Rules

### 7.1 Critical Context Requirement
**The AI MUST ALWAYS read the ENTIRE Conversation Thread.**  
Never generate a response using only the last email received. System prompts MUST pass historical messages sequentially to ensure context preservation.

### 7.2 OpenAI System Prompt Construction
The backend AI Service layer MUST construct requests using the following system prompt template:

```text
You are an expert internal customer support agent for our company.
Your role is to write clear, polite, concise, and highly effective email responses to customer inquiries based on the entire conversation history provided.

CRITICAL OPERATIONAL RULES:
1. ALWAYS preserve complete conversation context from the oldest message to the newest.
2. ALWAYS follow standard company Customer Support SOP.
3. NEVER promise monetary refunds, account credits, or custom discounts under any circumstances.
4. NEVER hallucinate policies, tracking numbers, or feature commitments not present in context.
5. ALWAYS detect the language of the customer's last message and write the entire reply in that customer's language.
6. Write directly in response format without preamble, metadata, or greetings like "Here is your draft:".
```

### 7.3 Token Management & Large Thread Handling
* If a conversation thread exceeds **12,000 tokens** (~40,000 characters), the backend service MUST perform automated sliding window context truncation:
  1. Retain System Prompt.
  2. Retain First Message (Original customer inquiry).
  3. Retain the most recent **N messages** fitting within the token budget.

---

## 8. API Integration Architecture

### 8.1 GoHighLevel Private Integration API
* **Base URL:** `https://services.leadconnectorhq.com`
* **Authorization:** `Bearer <GHL_PRIVATE_INTEGRATION_TOKEN>`
* **Headers:** `Version: 2021-07-28`, `Content-Type: application/json`

#### Endpoint 1: Fetch Thread Messages
```http
GET /conversations/{conversationId}/messages
```
* **Response Processing:** Map returned messages array into local `messages` table, normalizing timestamp and body content.

#### Endpoint 2: Send Outbound Email
```http
POST /conversations/messages
```
* **Request Payload:**
```json
{
  "type": "Email",
  "conversationId": "GHL_CONVERSATION_ID_HERE",
  "locationId": "GHL_LOCATION_ID_HERE",
  "message": "PlainText content from agent edited draft",
  "html": "<p>Formatted HTML content from agent edited draft</p>"
}
```

### 8.2 OpenAI API Integration
* **Endpoint:** `POST https://api.openai.com/v1/chat/completions`
* **Model:** `gpt-4o`
* **Payload Structure:**
```json
{
  "model": "gpt-4o",
  "temperature": 0.3,
  "messages": [
    {
      "role": "system",
      "content": "<SYSTEM_PROMPT_FROM_SECTION_7.2>"
    },
    {
      "role": "user",
      "content": "--- FULL CONVERSATION THREAD ---
Customer: Hi, where is order #8812?
Agent: Hi John, checking on this for you.
Customer: Thanks, please reply ASAP."
    }
  ]
}
```

---

## 9. Non-Functional Requirements

| Category | Requirement Specification |
|---|---|
| **Performance** | Webhook endpoint processing < 200ms. Full AI draft generation < 4 seconds. UI Inbox page switching < 300ms. |
| **Scalability** | Asynchronous queue worker handling up to 10,000 inbound emails daily without queue blocking. |
| **Security** | TLS 1.3 encryption for web interfaces. Encrypted storage of GHL API credentials using Laravel `encrypt()`. Protection against SQLi, XSS, and CSRF via standard Laravel middleware. |
| **Maintainability** | Clean Service-Repository pattern architecture in Laravel. No inline backend code in Blade templates. |
| **Code Quality** | Strict PSR-12 coding standard enforcement. PSR-4 autoloading. Standardized HTTP status codes and JSON response formats. |

---

## 10. User Stories & Acceptance Criteria

### US-001: Agent Authentication
**As a** Support Agent  
**I want to** log in with my email and password  
**So that** I can access the internal draft approval workspace.

#### Acceptance Criteria:
* [ ] Given an unauthenticated user, accessing `/inbox` redirects to `/login`.
* [ ] Given valid credentials, submitting the login form authenticates the user and redirects to `/inbox`.
* [ ] Given invalid credentials, appropriate error messaging appears on `/login`.

### US-002: Viewing Conversation Thread and Draft
**As a** Support Agent  
**I want to** view full conversation history along with pre-generated AI draft  
**So that** I can verify response accuracy without manual research.

#### Acceptance Criteria:
* [ ] Left panel lists all conversations with status `pending_review` by default.
* [ ] Clicking a conversation loads historical messages in chronological sequence in the center pane.
* [ ] The latest active draft is pre-filled inside the editable textarea.

### US-003: Regenerate AI Draft
**As a** Support Agent  
**I want to** click "Regenerate" when a draft does not meet quality standards  
**So that** the AI produces a fresh draft variant.

#### Acceptance Criteria:
* [ ] Clicking "Regenerate" shows a loading spinner on the button.
* [ ] Backend re-evaluates complete conversation context with OpenAI.
* [ ] Textarea updates with newly generated response upon completion.

### US-004: Send Approved Reply
**As a** Support Agent  
**I want to** click "Send" after reviewing or editing a draft  
**So that** the email reply is delivered to the customer via GoHighLevel.

#### Acceptance Criteria:
* [ ] Clicking "Send" locks UI controls to prevent double submissions.
* [ ] System dispatches text to GHL Conversations API `POST /conversations/messages`.
* [ ] Upon success, conversation status updates to `replied` and next pending item auto-loads.

---

## 11. Future Roadmap

### Version 1.0 (Current Scope - MVP)
* Webhook intake from GHL.
* Full thread context fetching.
* Auto-draft creation with OpenAI `gpt-4o`.
* Basic 2-screen interface (Login, Inbox).
* Single-click reply dispatch via GHL API.

### Version 2.0 (Post-MVP Enhancements)
* Configurable AI Tone controls (Formal, Concise, Friendly) directly in UI.
* Support for attachment metadata parsing.
* Multi-agent real-time lock indicator (prevent two agents reviewing the same draft simultaneously).
* Alternative AI Provider toggle (Claude 3.5 Sonnet / Gemini 1.5 Pro).

### Version 3.0 (Advanced Intelligence)
* Dynamic Knowledge Base RAG integration (embedding vector search for product manuals).
* Automatic sentiment detection and escalation flag.
* Internal team feedback loop on rejected/edited drafts to fine-tune AI prompts.
