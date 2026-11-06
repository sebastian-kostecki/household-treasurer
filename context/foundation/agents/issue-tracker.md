# Issue tracker: GitHub

Issues and specs for this repo live as GitHub issues in `sebastian-kostecki/household-treasurer`. Use the `gh` CLI for all operations; it infers the repo from `git remote -v`.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`. Use a heredoc for multi-line bodies.
- **Read an issue**: `gh issue view <number> --comments`.
- **List issues**: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` with `--label` and `--state` filters as needed.
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`. Label names: `.docs/agents/triage-labels.md`.
- **Close**: `gh issue close <number> --comment "..."`

GitHub shares one number space across issues and PRs, so a bare `#42` may be either: resolve with `gh issue view 42` and fall back to `gh pr view 42`.

## Pull requests as a triage surface

**PRs as a request surface: no.** External PRs are not treated as feature requests; triage covers issues only.

## When a skill says "publish to the issue tracker"

Create a GitHub issue.

## When a skill says "fetch the relevant ticket"

Run `gh issue view <number> --comments`.
