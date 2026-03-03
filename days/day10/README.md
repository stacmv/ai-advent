# Day 10: Context Management Strategies

## Overview

**Context Management Strategies** explores three fundamentally different approaches to managing conversation history in LLM-based applications when working within token limits.

Instead of the previous day's single "compression via summarization" approach, Day 10 compares **three distinct strategies** that trade off between **context preservation**, **token efficiency**, **feature richness**, and **implementation complexity**.

All three strategies are tested on the **same scenario**: a detailed technical interview about building an online bookstore system (Russian: "ТЗ" — technical specification).

---

## The Problem

As conversations grow longer, token usage increases, making it infeasible to send the entire history to the LLM every turn. Day 10 addresses this by comparing three solutions:

| Problem | Day 9 Solution | Day 10 Approach |
|---------|---|---|
| Long contexts cause token bloat | Summarize old messages | **Compare 3 alternative strategies** |
| One-size-fits-all approach | Always compress after N messages | **Choose strategy based on use case** |

---

## Three Context Management Strategies

### 1. **Sliding Window Strategy**

**Philosophy**: Keep it simple. Only the most recent N messages matter.

#### How It Works
- Store **all** messages on disk (for statistics & review)
- Context sent to LLM = **last 10 messages only**
- Early messages automatically disappear from context
- Simplest implementation, no LLM calls needed

#### Token Usage
```
Turn 1:  messages=[user, assistant]      → 2 messages in context
Turn 5:  messages=[user, assistant, ...] → 10 messages in context (max)
Turn 20: messages=[user, assistant, ...] → 10 messages in context (old ones dropped)
```

#### Storage
```
storage/day10_window.json
{
  "messages": [
    {"role": "user", "text": "..."},
    {"role": "assistant", "text": "..."},
    ...
  ]
}
storage/day10_window_tokens.json
{
  "totalInputTokens": 5000,
  "totalOutputTokens": 3000
}
```

#### Use Cases
✅ **Best for**: Customer support, Q&A bots, short-lived conversations
✅ Simple implementation
✅ Predictable memory footprint
❌ **Loses information**: Decisions made 15 turns ago are forgotten
❌ **Not ideal for**: Complex multi-turn planning

---

### 2. **Sticky Facts Strategy**

**Philosophy**: Extract and maintain key project facts across the entire conversation.

#### How It Works
1. Store **all** messages on disk
2. After each user message, **call LLM to extract facts** from entire conversation
3. Facts extracted: `goal`, `audience`, `budget`, `features`, `timeline`, `tech_stack`, `decisions`
4. Context sent to LLM = **facts as system message + last 6 user/assistant messages**
5. Facts automatically updated as conversation evolves

#### Token Usage
```
Turn 1:  facts extraction: +50 tokens, context: 6 messages
Turn 3:  facts extraction: +55 tokens, context: 6 messages
Turn 10: facts extraction: +65 tokens, context: 6 messages (with rich facts)
         Total extra tokens for facts: ~600 tokens
```

#### Storage
```
storage/day10_facts.json
{
  "messages": [...],
  "facts": {
    "goal": "Build online bookstore for university students and teachers",
    "audience": "Students and professors at Russian universities",
    "budget": "500,000 rubles max",
    "features": "Catalog, cart, card payment, 1C integration",
    "timeline": "3 months",
    "tech_stack": "(extracted from conversation)",
    "decisions": "Team of 3 developers, mobile app on iOS/Android"
  }
}
storage/day10_facts_tokens.json
{
  "totalInputTokens": 5500,    (includes facts extraction)
  "totalOutputTokens": 3600,   (includes facts extraction)
  "factsInputTokens": 500,     (separate tracking)
  "factsOutputTokens": 100
}
```

#### Implementation: Facts Extraction Prompt
```
Extract key project facts from this conversation as JSON with keys:
goal, audience, budget, features, timeline, tech_stack, decisions.
Use empty string for unknown fields. One sentence per value. Reply with JSON only.
Conversation:
[full conversation text]
```

#### Use Cases
✅ **Best for**: Project planning, requirements gathering, complex domain discussions
✅ Preserves critical information across long conversations
✅ Automatically maintains "living document" of project state
❌ **Extra cost**: ~10-15% more tokens (facts extraction)
❌ **Accuracy**: LLM-based extraction can miss nuances
❌ **Not ideal for**: Very short conversations (extraction overhead not worth it)

---

### 3. **Branching Strategy**

**Philosophy**: Explore alternative paths without losing the main thread.

#### How It Works
1. **Trunk**: Main conversation thread (all messages stored)
2. **Checkpoint**: Save current trunk state as reference point
3. **Branches**: Create named branches (A, B) that diverge from checkpoint
4. **Context on trunk**: All trunk messages
5. **Context on branch X**: Checkpoint messages + branch X messages
6. **Switch**: Can switch between branches or return to trunk

#### Flow Diagram
```
Initial turns 1-7 (trunk):
  User: "Need online bookstore"
  Agent: "..."
  ...

Checkpoint (save state):
  checkpoint = [turn1, turn2, ..., turn7]

Branch A:
  User: "Focus on budget version"
  Agent: "..."
  Context = [turn1..turn7] + [branchA messages]

Branch B:
  User: "Add mobile + 1C"
  Agent: "..."
  Context = [turn1..turn7] + [branchB messages]

Switch back to trunk (new turn 8):
  User: "Compare approaches"
  Agent: "..."
  Context = [turn1..turn7..turn8]
```

#### Storage
```
storage/day10_branching.json
{
  "trunk": [
    {"role": "user", "text": "..."},
    {"role": "assistant", "text": "..."},
    ...
  ],
  "checkpoint": [
    same as trunk messages up to checkpoint
  ],
  "branches": {
    "A": [
      {"role": "user", "text": "Focus on budget version"},
      {"role": "assistant", "text": "..."},
      ...
    ],
    "B": [
      {"role": "user", "text": "Add mobile + 1C"},
      {"role": "assistant", "text": "..."},
      ...
    ]
  },
  "active_branch": null  (null = trunk, "A" = branch A, "B" = branch B)
}
```

#### Use Cases
✅ **Best for**: Comparing alternatives, what-if analysis, design decisions
✅ **Rich context**: Can explore 2+ paths simultaneously
✅ **No information loss**: Can always return to trunk or switch branches
✅ **No extra API calls**: No LLM overhead
❌ **More complex**: Requires manual branch management
❌ **Higher token cost**: Duplicate checkpoint in each branch message

---

## Quick Start

### Run Day 10

```bash
# Start web UI (auto-detects day from git branch)
make up

# Click "Demo" button to run all 3 strategies on the same scenario
# Or use strategy buttons to switch between Window/Facts/Branching
# Type messages and see how each strategy handles context
```

### UI Layout

```
┌─ HEADER ───────────────────────────────────────────────────────────┐
│ Day 10: Context Management Strategies                              │
│ [Demo] [Clear History] [Record] [Stop] [Upload] [Clear Log]        │
│ ─────────────────────────────────────────────────────────────────  │
│ [Window] [Facts] [Branching]  ← Strategy Switcher                  │
│ ─────────────────────────────────────────────────────────────────  │
│ Sliding Window: last 10 messages of all stored conversations       │
│ (Strategy Panel updates based on active strategy)                  │
└─────────────────────────────────────────────────────────────────────┘
│ Chat Log (messages with token counts)                               │
├─────────────────────────────────────────────────────────────────────┤
│ Input field                                    [Send]               │
└─────────────────────────────────────────────────────────────────────┘
```

### Demo Cases

**Case 1: Sliding Window (10 turns)**
- Simple scenario: asks about tech stack, team organization, final spec
- Shows window size staying at 10, older messages disappearing
- Final summary uses only last 10 messages

**Case 2: Sticky Facts (10 turns)**
- Same scenario, but facts extracted after each turn
- Shows how facts panel updates with extracted data
- Later turns can reference early decisions (preserved in facts)
- Extra token cost visible in stats

**Case 3: Branching (17 turns total)**
- 7 turns on trunk: establish requirements
- Checkpoint: save state
- Branch A: "budget-minimum" version (no mobile, no 1C)
- Branch B: "full-featured" version (with mobile, with 1C)
- Switch back to trunk for comparison
- Shows how both branches preserve trunk context

---

## API Endpoints

### Strategy Management
- `GET /api/strategy` → `{"strategy": "window"|"facts"|"branching"}`
- `POST /api/strategy` → `{"strategy": "..."}` → switch active strategy

### Chat
- `POST /api/chat` → `{"message": "..."}` → returns response + token stats + strategy info
- `POST /api/chat/clear` → clear all stored messages for current strategy

### Strategy-Specific

**Sticky Facts:**
- `GET /api/facts` → `{"facts": {"goal":"...", "audience":"...", ...}}`

**Branching:**
- `GET /api/branches` → `{"activeBranch": null|"A"|"B", "branches": ["A", "B"], ...}`
- `POST /api/branch/checkpoint` → save current trunk state
- `POST /api/branch/create` → `{"name": "A"|"B"}` → create branch
- `POST /api/branch/switch` → `{"branch": "A"|"B"|null}` → switch to branch/trunk

### Recording & Upload
- `POST /api/record/start` → start ffmpeg screen capture
- `POST /api/record/stop` → stop recording gracefully
- `POST /api/upload` → upload latest video to Yandex.Disk

---

## Test Results: Same Scenario, Three Strategies

### Scenario

All three strategies were tested on the **same scenario**: planning an online bookstore for university students/professors (10-turn conversation about requirements, budget, team, tech stack, ending with "compile a summary spec").

### Token Usage (Actual Measurements)

| Strategy | Input Tokens | Output Tokens | Total | Overhead vs Window |
|----------|-------------|--------------|-------|-------------------|
| **Sliding Window** | 17 436 | 4 970 | **22 406** | baseline |
| **Sticky Facts** | 12 771 + 26 087 (facts) | 4 766 + 4 698 (facts) | **48 322** | **+116%** |
| **Branching** | 30 321 | 5 329 | **35 650** | +59% |

**Facts strategy** costs 2× more tokens because every turn triggers an additional LLM call to extract/update structured facts. The chat input tokens are actually *lower* (12 771 vs 17 436) because only 6 recent messages are sent, but the facts extraction calls (26 087 input) dominate the budget.

**Branching strategy** costs more because the trunk checkpoint (14 messages) is duplicated in each branch context, plus 12 additional branch messages across branches A and B.

### Quality of Final Response

| Criterion | Window | Facts | Branching |
|-----------|--------|-------|-----------|
| Audience (students/professors) retained? | **No** — lost | **Yes** — exact | A: Yes, B: vague |
| Budget (500K rubles) retained? | **Vague** — "plan the budget" | **Exact** — "500 000 rubles" | A: Yes, B: range 500K–1M |
| Timeline (3 months) retained? | Yes | Yes | Yes |
| Team (3 devs) retained? | Yes | Yes | Yes |
| Mobile app requirement? | Yes | Yes | A: excluded (correct), B: Yes |
| 1C integration? | Yes | Yes | Yes |
| Early-conversation details? | **Lost** after window slides | **All preserved** via facts | Good (via trunk checkpoint) |

### Stability (Information Loss Over Time)

**Sliding Window** — clearly loses early context. Audience and budget (defined in messages 2 and 4) disappeared from the final spec because they fell outside the 10-message window. The model compensated by being verbose and generic.

**Sticky Facts** — best retention. Every key detail from message 1 through message 20 was preserved in the structured facts object (`goal`, `audience`, `budget`, `features`, `timeline`, `tech_stack`, `decisions`). The final spec included even minor details like "personalized textbook recommendations" from message 3.

**Branching** — good retention within each branch thanks to the checkpoint mechanism. Branch A (budget variant) preserved all trunk details. Branch B (full variant) slightly lost audience specificity — a sign that branch-local messages can push early trunk details toward the edges of attention.

### User Experience

| Strategy | UX Quality | Manual Effort | Best Moment |
|----------|-----------|--------------|-------------|
| **Sliding Window** | Simple, familiar chat | None | Immediate — just type |
| **Sticky Facts** | Rich — facts panel shows extracted data updating live | None (automatic) | Seeing facts auto-populate after 3-4 turns |
| **Branching** | Powerful but complex — checkpoint/branch/switch buttons | Manual: create checkpoint, create branches, switch | Comparing two alternative specs side by side |

---

## Conclusions

### 1. No Single Winner
Each strategy excels in different scenarios:
- **Window**: Fast, simple, cheapest (REST APIs, chatbots, short conversations)
- **Facts**: Best information retention, highest token cost (project planning, requirements gathering)
- **Branching**: Unique ability to explore alternatives (architecture decisions, what-if analysis)

### 2. Facts Extraction: Quality vs Cost
- Facts strategy preserved **100% of key details** across 20 messages — no other strategy matched this
- But it cost **2× more tokens** than the baseline window approach
- The 10–15% overhead estimate from theory turned out to be **116% in practice** because facts extraction sends the *entire* conversation each time
- Trade-off is worthwhile for high-stakes conversations (project specs, contracts) but overkill for casual Q&A

### 3. Window Strategy: The "Forgetting" Problem Is Real
- On a 10-turn planning conversation, the window strategy lost **audience** and **budget** — two of the most important project parameters
- This isn't theoretical — it happened on a standard ТЗ-gathering scenario
- For conversations longer than the window size, this strategy is **unreliable for planning tasks**

### 4. Branching: Unique Value, Unique Complexity
- The only strategy that produced **two alternative specs** from one conversation
- Branch B estimated a realistic 500K–1M range instead of blindly repeating 500K — qualitatively better reasoning
- But requires manual checkpoint/branch/switch management — not suitable for all users

### 5. Real-World Hybrid Approach
In production, the optimal approach would combine strategies:
- Start with **Window** for initial turns (cheap, no overhead)
- Switch to **Facts** when conversation becomes complex (automatic, preserves everything)
- Use **Branching** only at critical decision points (manual, but powerful)

### 6. Facts Extraction Format Matters
- Structured extraction (7 named fields) worked better than free-form summarization (Day 9)
- The model's responses started echoing the facts template structure — a side effect that actually improved consistency
- Could be combined with user-editable facts for highest reliability

---

## Implementation Details

### Bug Fixed During Development
**Message Format Error**: Initial implementation used `'content'` field (OpenAI format) instead of `'text'` (YandexGPT format), causing 400 errors. Fixed with graceful error handling in demo runner.

### Error Handling
Demo automatically stops with clear messages if:
- API returns error (bad credentials, invalid folder ID)
- LLM returns error response
- Network or other exceptions occur

### Persistent Storage
Each strategy maintains separate JSON files:
- `storage/day10_window.json` + `day10_window_tokens.json`
- `storage/day10_facts.json` + `day10_facts_tokens.json`
- `storage/day10_branching.json` + `day10_branching_tokens.json`
- `storage/day10_config.json` (current active strategy)

Files persist across PHP requests, enabling token accumulation and history review.

---

## Files

| File | Purpose |
|------|---------|
| `days/day10/web.php` | Main implementation (3 strategies + API + UI) |
| `days/day10/demo_cases.php` | Demo test cases for all 3 strategies |
| `days/day10/README.md` | This file |

---

## Recommendations for Future Work

1. **Adaptive Strategy Selection**: Automatically switch strategies based on conversation state
2. **Facts Validation**: Add user approval step before saving extracted facts
3. **Hybrid Window+Facts**: Use window for recent messages + facts summary for older context
4. **Branching Merge**: Allow merging decisions from branches back to trunk
5. **Multi-Language Facts**: Extract facts in conversation's original language

---

## Related Days

- **Day 9** (Context Compression): Single approach using summarization
- **Day 8** (Token Counting): Tracking tokens across turns
- **Day 7** (Persistent History): Saving conversation state to disk
- **Day 6** (Agent Architecture): Web UI for LLM interactions

---

## Summary

Day 10 demonstrates that **there is no universal solution** to context management. The best strategy depends on:
- **Conversation type**: Stateless (window) vs. structured (facts) vs. exploratory (branching)
- **Token budget**: Can afford extra LLM calls? (facts) Or keep overhead at zero? (window/branching)
- **User needs**: Simple interaction (window) vs. rich state tracking (facts) vs. decision exploration (branching)

By comparing three strategies on the same scenario, this project provides a framework for choosing the right approach for your specific use case.

