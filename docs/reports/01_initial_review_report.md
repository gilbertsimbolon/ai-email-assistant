# Initial Review Report — AI Email Assistant

## 1. Project Summary

**AI Email Assistant** is an internal tool for customer support agents, built on **Laravel 13 / PHP 8.3**, with **MySQL** storage. It is explicitly scoped as **not** a CRM/helpdesk/SaaS product — single-tenant, internal use only. The core loop: a customer email arrives in **GoHighLevel (GHL)**, GHL fires a webhook into this Laravel app, the app pulls the full conversation thread from GHL, sends it to **OpenAI (gpt-4o)** to generate a draft reply, a human agent reviews/edits/regenerates the draft in a Blade-based Inbox UI, and only then does the agent click "Send," which pushes the reply back out through the GHL API. The AI **never sends automatically** — human approval is a hard requirement (SOP.md). Stated goal: 80% reduction in support response time.

⚠️ **Important caveat on sourcing**: the working-tree `docs/*.md` files are currently near-empty one-line stubs. The substantive specification only exists in **git history**, in the original docs from commit `c9a200e` (`PRD.md`, `CLAUDE.md`, `DATABASE.md`, `API_MAPPING.md`, `SOP.md`, `PROMPTS.md`, `TASKS.md`), which were gutted in commit `71c6443`. Everything below marked "per original docs" is recovered from that commit, not the current file contents — see **Risks** for why this matters.

## 2. Business Requirements

- **Users**: internal support agents only, single `web` guard login, unauthenticated → redirect to `/login`.
- **Inbox UI**: two-column layout — 30% conversation list (filterable by "Pending Review" / "Replied"), 70% detail + draft workspace.
- **Draft lifecycle**: `active` → `regenerated` / `sent` / `discarded` (per PRD; DATABASE.md's original doc used a different enum — see Risks).
- **AI guardrails (SOP.md)**: never promise refunds/discounts/compensation, never invent policy, never guess, never blame customer/staff, escalate legal threats/abuse/unresolved payment disputes, reply in the customer's detected language, structure emails Greeting → Acknowledgement → Response → Next Steps → Closing, never expose API keys/internal notes/PII.
- **Non-functional targets**: webhook responds <200ms (work deferred to a queued job), AI draft generation <4s, page navigation <300ms, up to 10,000 inbound emails/day, TLS 1.3, GHL credentials encrypted at rest via Laravel `encrypt()`.
- **Roadmap**: v1.0 MVP (above) → v2.0 (tone controls, attachments, multi-agent locking, Claude/Gemini provider toggle) → v3.0 (RAG, sentiment analysis, fine-tuning feedback loop).

## 3. Technical Architecture

Prescribed pattern (CLAUDE.md/ARCHITECTURE.md): thin controllers → **Service layer** → Repository/Eloquent → external API/DB, dependency-injected, PSR-12/PSR-4.

Named services the docs expect to exist: `ConversationService`, `GoHighLevelService`, `OpenAIService`, `PromptService`, `DraftService`.

Flow: `Customer Email → GHL inbound webhook → POST /api/v1/webhooks/ghl → ProcessInboundEmailJob (queued) → GHL GET /conversations/{id}/messages → OpenAI Chat Completions → Draft saved (status=active) → Agent reviews in Inbox → POST /conversations/{id}/regenerate (AJAX) or POST /conversations/{id}/send (AJAX) → GHL POST /conversations/messages → draft.status=sent, conversation.status=replied`.

**Current reality**: this is essentially a stock `laravel new` skeleton (composer.json still literally named `laravel/laravel`). No `app/Services`, `app/Repositories`, or `app/Jobs` directories exist. No `Conversation`/`Message`/`Draft` models. Only route is `GET /` → stock `welcome` view. No `routes/api.php` at all. No auth scaffolding (TASKS.md calls for installing Laravel Breeze; not installed). `config/services.php` has only stock Postmark/Resend/SES/Slack keys — nothing for OpenAI or GHL. No OpenAI SDK, GHL SDK, or Guzzle explicitly added in `composer.json`.

## 4. Database Understanding

Original `DATABASE.md`/`PRD.md` describe an ERD `users → conversations → messages → drafts`, but **the two original docs disagree with each other** on column names/enums for the same tables:

| Table | PRD.md version | DATABASE.md version |
|---|---|---|
| conversations | `ghl_conversation_id`, `ghl_location_id`, `contact_name`, `contact_email`, `status` enum(pending_review/replied/failed) | `ghl_id`, `contact_id`, `subject`, `last_message`, `unread_count`, `synced_at` |
| messages | `ghl_message_id`, `sender_type` enum(customer/agent/system) | `ghl_message_id`, `sender`, `direction` enum(inbound/outbound), `message_type` |
| drafts | `status` enum(active/regenerated/sent/discarded) | `status` enum(pending/sent/rejected), `provider` (openai/claude/gemini) |

Neither version exists as an actual migration — only the 3 stock Laravel migrations (`users`, `cache`, `jobs`) are present. "Future, not-MVP" tables noted: `ai_logs`, `webhook_logs`, `prompt_templates`, `sop_documents`, `audit_logs`. Explicit rule: "Never store API tokens in the database" (they belong in `.env`, encrypted where transmitted).

## 5. API Integration Understanding

- **GoHighLevel** — Private Integration API v2, base `https://services.leadconnectorhq.com`, headers `Authorization: Bearer <token>` + `Version: 2021-07-28`. Endpoints: `GET /conversations`, `GET /conversations/{id}/messages` (must fetch full thread, not just latest email), `GET /contacts/{contactId}` (optional), `POST /conversations/messages` (send). Error handling for 401/403/404/429/500. Logging rule: log URL/status/latency/conversation ID, **never** log tokens/secrets.
- **OpenAI** — model `gpt-4o` (configurable), Chat Completions API, `temperature: 0.3`, `max_tokens: 1200`, `top_p: 1.0`, penalties 0. Sliding-window truncation if thread exceeds ~12,000 tokens (~40,000 chars). A full literal system prompt is specified in PRD.md §7, plus 6 more named prompt templates in `PROMPTS.md` (draft generation, regenerate, conversation summary, sentiment detection, language detection, internal notes), with template variables `{{conversation}}`, `{{customer_name}}`, `{{customer_email}}`, `{{company_name}}`, `{{sop}}`, `{{agent_name}}`.
- **Current reality**: zero integration code exists. `.env.example` has no `OPENAI_API_KEY`, no `GHL_*` vars at all, despite both being required per docs. `DB_DATABASE=ai_email_assistant` and `DB_CONNECTION=mysql` are pre-set, suggesting someone started environment prep but stopped before adding the API credentials.

## 6. Frontend Understanding

- **`public/sneat/`** is the raw, unmodified **ThemeSelection "Sneat" free Bootstrap 5 admin template** (~14MB, 214 files, added wholesale in one commit). It ships its own competing build tooling (gulp/webpack/its own package.json) and 39 static demo HTML pages — including `auth-login-basic.html` (useful starting point for `/login`), but **no ready-made inbox/two-column-messaging page**; that screen would need to be assembled from `layouts-*`, `cards-basic`, `ui-list-groups`, `forms-*` primitives.
- **It is completely unwired into Blade.** Only one Blade view exists: `resources/views/welcome.blade.php`, the stock Laravel 13 starter-kit page using `@vite` + **Tailwind CSS** utility classes.
- **Stack conflict**: docs (`CLAUDE.md`, `PRD.md`, and the current stub `06_FRONTEND.md`) mandate "Blade & Bootstrap 5" / "use public/sneat, Bootstrap only," but the live Vite pipeline (`vite.config.js`, `resources/css/app.css`, root `package.json`) is Tailwind-based with **no Bootstrap package, no jQuery, and no reference to any Sneat asset anywhere**. Nothing currently bridges the two — this needs an explicit decision (serve Sneat's compiled `assets/vendor/css/core.css` directly via `<link>` tags bypassing Vite, vs. migrating Sneat's SCSS/JS into the Vite pipeline, vs. dropping Tailwind and rebuilding the starter kit around Bootstrap).

## 7. Risks

1. **Documentation regression (highest priority)**: commit `71c6443` deleted 1,228 lines of specification across 7 files and replaced them with 31 lines of stub content. Anyone (human or AI) reading only the current `docs/` tree has almost no usable spec. This looks like an accidental overwrite, not an intentional simplification — worth confirming with the user before doing anything else, since "the documentation inside docs is the source of truth" per the review instructions, and that source is currently hollowed out.
2. **Internal spec inconsistency**: PRD.md and DATABASE.md (original versions) disagree on `conversations`/`messages`/`drafts` schema — must be reconciled into one authoritative DDL before any migration is written.
3. **Frontend stack conflict**: Bootstrap (Sneat) vs. Tailwind (Laravel 13 default starter) — unresolved, blocks any real UI work.
4. **No auth scaffolding**: TASKS.md calls for Laravel Breeze; not installed. Login/session flow needs to be built or scaffolded before Inbox work.
5. **Missing credentials/config**: no `OPENAI_API_KEY` or `GHL_*` env vars defined anywhere, even as placeholders in `.env.example`.
6. **Sneat template is a large, disconnected asset tree** (own gulp/webpack build) sitting in `public/`, which — if left as-is — will need careful handling to avoid it becoming stale, duplicative, or a maintenance burden alongside Vite.

## 8. Missing Requirements

- No restored/authoritative `docs/*.md` content to build against (see Risk 1) — until this is fixed, "the docs are the source of truth" instruction can't actually be honored from the working tree alone.
- No decision on GHL webhook **authentication/verification** (signature validation? IP allowlist?) — original docs don't specify this.
- No decision on **queue driver** for `ProcessInboundEmailJob`/`SendGhlEmailJob` in production (currently `QUEUE_CONNECTION=database`, fine for MVP but worth confirming).
- No spec for what happens if OpenAI or GHL calls fail/time out mid-flow (retry policy, user-facing error state in Inbox).
- No test plan detail beyond "Feature tests for flows, mock GHL and AI APIs" — no concrete fixture/mocking strategy documented.

## 9. Questions Before Implementation

1. Was the deletion of the original docs content in commit `71c6443` intentional? Should the original PRD/CLAUDE/DATABASE/API_MAPPING/SOP/PROMPTS/TASKS content be restored into the numbered `docs/` files before implementation starts?
2. Which conversations/messages/drafts schema is authoritative — PRD.md's or DATABASE.md's — or should a reconciled version be proposed?
3. Bootstrap (Sneat) vs. Tailwind: keep Sneat and rip out the Tailwind starter kit (dropping `@tailwindcss/vite`, adding Bootstrap/jQuery to the Vite pipeline), or drop Sneat and build the UI in Tailwind instead? This affects nearly every subsequent frontend task.
4. Should Laravel Breeze be installed for auth, or is a custom minimal login preferred (per CLAUDE.md's "simplicity first, no over-engineering" principle)?
5. Is a GHL Private Integration token and OpenAI API key ready to drop into `.env` for local development, or should early work stub/mock these integrations?
6. Should implementation proceed sprint-by-sprint per `07_TASKS.md`'s original 12-phase roadmap (Setup → Auth → DB → GHL → Inbox → AI → Reply → Logging → Testing → Security → Performance → Deployment), starting with Phase 1?

No code was written or files modified during the review itself, per the original review instructions. This report was saved per explicit follow-up request.
