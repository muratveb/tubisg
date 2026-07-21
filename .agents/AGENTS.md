# Tubİsg - AI Agent Rules & Behavioral Directives

These project rules are mandatory for all AI coding assistants working on the Tubİsg codebase under `/Applications/MAMP/htdocs/tubisg`.

---

## 🔒 Rule 1: Immutable Master Admin User Protection
- The user account with username `admin` (or user ID `1`) is the **immutable master system administrator**.
- **STRICT PROHIBITION**: Under NO CIRCUMSTANCES can any user, API request, form submission, or database query (even from other Super Admin accounts) delete, deactivate, demote the role of, or block the master `admin` user.
- Any attempt to delete or disable the `admin` user must be blocked immediately with an explicit warning message.

---

## 🚀 Rule 2: Automatic Git Commit & Push Policy
- **AUTOMATIC EXECUTION**: After completing ANY code modification, bug fix, layout enhancement, or feature addition requested by the user, the AI assistant MUST automatically run:
  1. `git add .`
  2. `git commit -m "<descriptive message>"`
  3. `git push origin main`
- The AI should execute this git sync workflow proactively without waiting for explicit prompt instructions from the user.

---

## 📝 Rule 3: Continuous Markdown Log & Spec Updating
- Always update `DEVELOPMENT_LOG.md` and `TUBISG_PROJECT_SPEC.md` whenever architectural changes, new endpoints, or UI features are added.
