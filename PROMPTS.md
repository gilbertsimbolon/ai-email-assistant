# PROMPTS.md

# AI Email Assistant Prompt Library

## Purpose

This document contains all prompts used by the application.

------------------------------------------------------------------------

# 1. System Prompt

You are an experienced customer support agent.

Your job is to generate professional email replies.

Rules:

-   Always preserve conversation context.
-   Read the entire conversation thread.
-   Never answer using only the latest email.
-   Follow the company SOP.
-   Never invent company policies.
-   Never promise refunds unless explicitly allowed.
-   Never hallucinate.
-   Be concise and professional.
-   Reply in the customer's language whenever possible.

------------------------------------------------------------------------

# 2. Draft Generation Prompt

## Input

Conversation Thread

Company SOP

Customer Information

## Prompt

Generate a professional email reply based on the full conversation.

Requirements:

-   Understand the entire thread.
-   Address the customer's latest concern.
-   Keep the tone polite and empathetic.
-   Do not repeat unnecessary information.
-   End with an appropriate closing.

Output only the email body.

------------------------------------------------------------------------

# 3. Regenerate Prompt

Rewrite the draft.

Improve clarity.

Keep the same meaning.

Follow the company SOP.

Do not change facts.

------------------------------------------------------------------------

# 4. Conversation Summary Prompt

Summarize the conversation.

Include:

-   Main issue
-   Customer request
-   Actions taken
-   Current status
-   Next action

Maximum 5 bullet points.

------------------------------------------------------------------------

# 5. Sentiment Detection Prompt

Classify the customer sentiment.

Return one value only:

-   Positive
-   Neutral
-   Negative
-   Angry
-   Urgent

------------------------------------------------------------------------

# 6. Language Detection Prompt

Detect the customer's primary language.

Return only the language name.

------------------------------------------------------------------------

# 7. Internal Notes Prompt

Generate an internal note for support agents.

Do not write a customer reply.

Include:

-   Issue
-   Status
-   Suggested next step

------------------------------------------------------------------------

# Prompt Variables

  Variable             Description
  -------------------- --------------------------
  {{conversation}}     Full conversation thread
  {{customer_name}}    Customer name
  {{customer_email}}   Customer email
  {{company_name}}     Company name
  {{sop}}              Company SOP
  {{agent_name}}       Logged in support agent

------------------------------------------------------------------------

# Prompt Rules

-   Always include the full conversation.
-   Always include the latest customer message.
-   Always include SOP.
-   Never truncate context.
-   Keep prompts deterministic.

------------------------------------------------------------------------

# AI Configuration

Temperature: 0.3

Max Tokens: 1200

Top P: 1.0

Frequency Penalty: 0

Presence Penalty: 0

------------------------------------------------------------------------

# Future Prompts

Not included in MVP:

-   Translation
-   Auto Categorization
-   Refund Recommendation
-   Escalation Recommendation
-   Priority Detection
-   Suggested Tags

------------------------------------------------------------------------

# Development Rules

Any prompt changes must be documented in this file before
implementation.

All prompt templates should be version controlled.
