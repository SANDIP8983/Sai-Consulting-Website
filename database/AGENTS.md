# Sai Consulting Website - AI Development Rules

## Technology Stack
- Laravel 12
- PHP 8.4
- Bootstrap 5
- MySQL
- MVC Architecture

## Coding Standards
- Production-ready code only.
- Follow Laravel best practices.
- Never hardcode configuration values.
- Use validation for all user inputs.
- Keep controllers thin.
- Use services where business logic is required.
- Follow existing project structure.
- Write clean and readable code.

## Database
- Use foreign keys where applicable.
- Use soft deletes only when required.
- Never remove existing tables without approval.
- Create new migrations instead of editing old migrations unless instructed.

## Development Rules
- Analyze existing code before making changes.
- Do not rename existing files unless requested.
- Ask before making breaking changes.
- Keep changes small and reviewable.

## UI
- Bootstrap 5 only.
- Responsive design.
- Professional business appearance.

## Git
- One logical change per commit.
- Use meaningful commit messages.

## Business Rules
- Website: Sai Consulting
- Public contact: Email, WhatsApp, Contact Form
- No Aadhaar/PAN uploads on website.
- Support online and offline request workflow.
- Reference numbers must remain unique.
- Admin manages settings, office timings, holidays, and website status.

## Response Style
Before coding:
1. Explain the plan.
2. List files that will change.
3. Wait if a breaking change is required.

After coding:
1. Summarize changes.
2. Mention any migrations or commands to run.
3. Suggest a Git commit message.