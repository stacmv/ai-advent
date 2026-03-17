# Day 12: Personalized Assistant

## Overview

Day 12 extends Day 11's memory model with **explicit structured profiles** and **side-by-side comparison**. Instead of a simple key-value user profile, we define a rich profile schema with specific personalization dimensions (expertise, style, format, language, restrictions, inclusions). The same question gets demonstrably different answers depending on which profile is active.

## Key Features

### 1. Structured Profile Schema

Each profile has these fields:
- **role**: User's role (e.g., "Senior Engineer", "Manager", "Student")
- **expertise**: `beginner` | `intermediate` | `expert`
- **style**: `formal` | `casual` | `technical` | `friendly`
- **format**: `prose` | `bullets` | `code_first` | `concise`
- **depth**: `brief` | `standard` | `detailed`
- **language**: Language code (en, es, fr, de, ru, zh, ja)
- **avoid**: Array of topics/jargon to exclude
- **always_include**: Array of elements to always add (examples, edge cases, performance notes)

### 2. Profile Presets

Four built-in profiles demonstrate different combinations:

**Beginner**
- Friendly tone, prose format, standard depth
- Avoids: jargon, advanced concepts
- Includes: simple examples, step-by-step explanations

**Expert**
- Technical tone, code-first format, detailed depth
- Avoids: oversimplifications, basic explanations
- Includes: performance implications, edge cases

**Business**
- Formal tone, bullet format, brief depth
- Avoids: technical jargon, implementation details
- Includes: business impact, ROI considerations

**Casual**
- Casual tone, prose format, standard depth
- Includes: analogies, real-world examples

### 3. Personalization-to-System-Prompt Translation

The `buildPersonalizationPrompt()` method translates profile fields into concrete directives injected into the LLM system prompt:

```
## Personalization Directives
Explain all concepts from scratch, assume no prior knowledge.
Use casual, friendly, conversational tone.
Write in flowing prose paragraphs.
Always include: simple examples, step-by-step explanations.
```

This ensures the LLM actually follows the personalization, not just suggests it.

### 4. Profile Management

- **Save Profile**: Create and name new profiles
- **Load Profile**: Set active profile (updates badge, affects all chat)
- **Delete Profile**: Remove saved profiles
- **List Profiles**: View all saved profiles with metadata

### 5. Side-by-Side Comparison

Ask the same question with two different profiles selected, get responses rendered in parallel columns. Color-coded (blue for A, green for B) to show the contrast visually.

## Architecture

### Files

- `web.php` - PersonalizedAgent class (extends MemoryModel), API routes
- `web.php.html` - UI with 3 tabs (Profile Builder, Chat, Compare)
- `demo_cases.php` - 3 automated demo scenarios
- `README.md` - This file

### PersonalizedAgent Class

Extends `MemoryModel` from Day 11 with:

```php
class PersonalizedAgent extends MemoryModel {
    public function saveProfile($name, $data)
    public function loadProfileByName($name)
    public function listProfiles()
    public function deleteProfile($name)
    public function getActiveProfile()
    public function buildPersonalizationPrompt($profile)
}
```

### Storage Files

- `storage/day12_shortterm.json` - Recent messages (last 10, sliding window)
- `storage/day12_profiles.json` - All saved profiles as JSON object
- `storage/day12_active.json` - Currently active profile reference
- `storage/day12_working.json` - Task state (from MemoryModel)
- `storage/day12_longterm.json` - Knowledge base (from MemoryModel)

### API Endpoints

All POST/GET at `/days/day12/api/*`:

- `POST /api/chat` - Send message with active profile (includes personalization)
- `POST /api/compare` - Get responses from two profiles simultaneously
- `GET /api/profiles` - List all saved profiles
- `POST /api/profiles/save` - Save a new profile
- `POST /api/profiles/load` - Set active profile
- `POST /api/profiles/delete` - Remove a profile
- `GET /api/active-profile` - Get current active profile
- `POST /api/memory/clear` - Clear chat history
- `POST /api/demo/run` - Run automated demo by case number

## UI Layout

### Tab 1: Profile Builder
**Left column**: Form to create/edit profiles with all fields
**Right column**:
- Saved profiles list with Load/Delete buttons per profile
- Quick preset buttons (Load Beginner, Load Expert, Load Business, Load Casual)

### Tab 2: Chat
- Active profile badge showing current profile + key settings
- Chat log (user messages blue right, assistant grey left)
- Input field + Send button

### Tab 3: Compare
- Profile A dropdown selector
- Profile B dropdown selector
- Question textarea
- Compare button
- Side-by-side response panels (A=blue, B=green)

## Demo Cases

### Case 1: Expertise Impact
Load Beginner profile, ask "What is dependency injection and why is it useful?"
Then load Expert profile, ask the same question.
Shows: Depth, terminology, and explanation style differences.

### Case 2: Format Preferences
Load Business profile (bullets, brief), ask about pagination.
Then load Casual profile (prose, standard), ask same question.
Shows: Structured vs flowing, detailed vs concise.

### Case 3: Personalization Contrast
Compare Beginner vs Expert profiles on "Explain how database indexing works"
Side-by-side shows: Depth, jargon level, and structure differences.

## Usage

### Start Web Server
```bash
make up
```
Open http://localhost:8080/days/day12

### Run Demo
```bash
make demo
```

### Record & Upload
```bash
make record     # Record screen + run demo
make upload     # Upload latest video
```

## Token Cost Comparison

Compared to Day 11 (which includes memory layers in system prompt):

- **Day 11**: System prompt with all 3 memory layers = ~600 tokens/turn
- **Day 12**: System prompt with personalization + memory layers = ~700 tokens/turn
- **Compare endpoint**: Two parallel LLM calls = ~1400 tokens

Small overhead from personalization directives, but enables precise control over response style. The extra cost is justified by the demonstrable behavioral changes.

## Key Differences from Day 11

| Aspect | Day 11 | Day 12 |
|---|---|---|
| Profile Storage | Single active key-value map | Multiple named profiles + active |
| Profile Schema | Freeform | Structured (15+ standard fields) |
| Personalization | Via context only | Via system prompt directives |
| Demo | Shows profile impact | Shows side-by-side profile comparison |
| UI | Memory layers + chat | Profile builder + chat + comparison |
| API Calls | 1 per turn | 1 (chat) or 2 (compare) |

## Technical Notes

### System Prompt Injection Pattern

The system prompt structure:
```
You are a helpful AI assistant personalized for the user.

## Personalization Directives
[expertise level]
[style]
[format]
[language]
[avoid directives]
[always include directives]

Use the following context:

[memory layers from buildContext()]

Respond helpfully based on the personalization directives and context.
```

This ensures personalization directives are evaluated *before* context, making them higher priority.

### Compare Endpoint

The compare endpoint:
1. Takes a question + two profile names
2. Builds two system prompts independently
3. Makes two parallel LLM calls (with 500ms delay to avoid rate limiting)
4. Returns both responses for side-by-side rendering

This demonstrates how the exact same model and question produces different outputs based only on system prompt personalization.

### Storage Isolation

Each day has its own storage files (day12_*.json), allowing Day 12 to coexist with other days without conflicts. Clearing Day 12 memory doesn't affect Day 11 or other days.

## Validation

Test the following:
1. ✅ Create beginner and expert profiles in Profile Builder
2. ✅ Load beginner, ask technical question → observe simplified response
3. ✅ Load expert, ask same question → observe technical response
4. ✅ Select both in Compare tab → side-by-side shows clear contrast
5. ✅ Run Demo 1 → automated sequence shows expertise impact
6. ✅ Switch profiles, continue chat → active badge updates, responses change
7. ✅ Clear memory → chat history cleared, profiles persist

## Future Extensions

- Profile inheritance (templates)
- Profile versioning / history
- Export/import profiles as JSON
- Dynamic profile creation from responses
- A/B testing framework (track which profile performed better)
