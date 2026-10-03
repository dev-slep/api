# CLAUDE.md

@AGENTS.md

## Git: never commit without checking with the user

- **Do not commit** (or amend, rebase, merge, tag or push) unless the user has just asked you to in this conversation. Finishing a task, passing the tests or a task file saying "commit" is not permission.
- When work is ready to commit, say what changed and in which repos, and **ask**. Show `git status` or a short list of files if it helps.
- Permission is for that one commit. Ask again next time.
- Leave changes uncommitted by default. Renaming a task file with `done-` is also a change to commit: ask first.
- Each repo (`app`, `api`, `tasks`, `internal-docs`) is its own git repository; ask per repo.
