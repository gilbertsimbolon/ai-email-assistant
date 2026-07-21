# DATABASE.md

# AI Email Assistant Database Design

## Purpose

This document defines the database schema for the Laravel application.

The database stores only the information required by the application.
GoHighLevel remains the source of truth for conversations.

------------------------------------------------------------------------

# Design Principles

-   Minimize duplicated data.
-   Store GoHighLevel IDs for synchronization.
-   Preserve AI drafts.
-   Keep tables normalized.
-   Use Laravel conventions.

------------------------------------------------------------------------

# Entity Relationship

``` text
users
  |
  | 1..N
  |
conversations
  |
  | 1..N
  |
messages
  |
  | 1..N
  |
drafts
```

------------------------------------------------------------------------

# Tables

## users

  Column       Type        Notes
  ------------ ----------- --------
  id           bigint      PK
  name         string      
  email        string      unique
  password     string      
  created_at   timestamp   
  updated_at   timestamp   

------------------------------------------------------------------------

## conversations

  Column         Type                 Notes
  -------------- -------------------- ----------------
  id             bigint               PK
  ghl_id         string               unique
  contact_id     string               GHL Contact ID
  subject        string nullable      
  last_message   text nullable        
  unread_count   integer              default 0
  synced_at      timestamp nullable   
  created_at     timestamp            
  updated_at     timestamp            

Indexes

-   ghl_id
-   contact_id

------------------------------------------------------------------------

## messages

  Column            Type                 Notes
  ----------------- -------------------- ------------------
  id                bigint               PK
  conversation_id   FK                   conversations.id
  ghl_message_id    string               unique
  sender            string               customer/agent
  direction         string               inbound/outbound
  message_type      string               email
  body              longText             
  sent_at           timestamp nullable   
  created_at        timestamp            
  updated_at        timestamp            

Indexes

-   conversation_id
-   ghl_message_id

------------------------------------------------------------------------

## drafts

  Column            Type          Notes
  ----------------- ------------- -----------------------
  id                bigint        PK
  conversation_id   FK            conversations.id
  message_id        FK nullable   messages.id
  provider          string        openai/claude/gemini
  prompt            longText      optional
  draft             longText      generated reply
  status            string        pending/sent/rejected
  generated_at      timestamp     
  created_at        timestamp     
  updated_at        timestamp     

------------------------------------------------------------------------

# Relationships

User - hasMany Conversations (future)

Conversation - hasMany Messages - hasMany Drafts

Message - belongsTo Conversation

Draft - belongsTo Conversation - belongsTo Message (optional)

------------------------------------------------------------------------

# Synchronization Strategy

-   GoHighLevel is the source of truth.
-   Store GHL IDs locally.
-   Sync conversations when opened or via webhook.
-   Never overwrite drafts after sending.

------------------------------------------------------------------------

# Migration Order

1.  users
2.  conversations
3.  messages
4.  drafts

------------------------------------------------------------------------

# Eloquent Models

-   User
-   Conversation
-   Message
-   Draft

------------------------------------------------------------------------

# Future Tables (Not MVP)

-   ai_logs
-   webhook_logs
-   prompt_templates
-   sop_documents
-   audit_logs

------------------------------------------------------------------------

# Development Rules

-   Use foreign keys.
-   Use cascade deletes where appropriate.
-   Use Eloquent relationships.
-   Avoid duplicate conversation records.
-   Never store API tokens in the database.
