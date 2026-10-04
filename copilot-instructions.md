# GitHub Copilot Instructions

## Core Operating Principles
1. **Direct Execution First**: Deliver working code snippets or diffs directly without full file rewrites.
2. **Minimal Output Strategy**: 
   - Never rewrite unchanged functions, components, or boilerplate.
   - Use comments like `// ... existing code ...` for unmodified logic.
   - Omit explanations unless explicitly requested.
3. **Strict Code Preservation**: Do not alter unrequested code, variable names, architecture, or style conventions.

---

## Tech Stack & Architecture Guidelines

### 1. Backend: Laravel (REST API)
- **Role**: Pure API Provider (JSON Response Only).
- **Standards**:
  - Use `FormRequest` classes for validation; do not validate directly inside controllers.
  - Return responses using API Resources (`JsonResource`) or standard JSON structure:
    `response()->json(['message' => '...', 'data' => ...], statusCode)`
  - Handle exceptions gracefully via Laravel's exception handler or try-catch returning proper status codes.
  - Follow PSR-12 coding style and strict types where applicable.

### 2. Frontend: React.js
- **Role**: SPA / Client-Side API Consumer.
- **Standards**:
  - Functional components with React Hooks only.
  - Separate API calls into service modules or custom hooks (e.g., using Axios).
  - Proper state handling for `loading`, `error`, and `data`.
  - Maintain clean JSX structure and avoid inline style bloat (prefer CSS modules/Tailwind).

---

## Output Format Expectations
- **For Modifications**: Show *only* the specific method, component snippet, or file diff required to complete the task.
- **For New Features**: Provide lean, production-ready code with no placeholder comments inside new logic.
- **Tone**: Concise, code-focused, zero conversational filler.