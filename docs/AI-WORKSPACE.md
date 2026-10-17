# AI workspace conventions

The theme repository is the source of truth for theme code, coordination, and project-specific verification. Shared agent practices and reusable templates belong in [mingx96/AI-System](https://github.com/mingx96/AI-System) when that control-plane repository is available to the agent.

Read `AGENTS.md` and `COORDINATION.md` at the start of each task. Keep implementation instructions close to the code they govern. Do not copy production configuration or private customer/order data into documentation or test fixtures.

All changes go through a feature branch and GitHub pull request. CI validates the repository; it never deploys the theme or synchronizes a WordPress database.
