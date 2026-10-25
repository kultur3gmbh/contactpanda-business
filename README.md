# ContactPanda Business

ContactPanda Business is the project and work-management extension built on the ContactPanda Pro commercial foundation. It provides projects, tasks, phases, responsibilities, due dates, reminders, time tracking, budgets, files, customer collaboration, corrections, approvals, capacity planning, and project reports.

Business requires compatible Basic and Pro installations. Historical Business-owned customer, contract, subscription, payment, and software-licensing module claims have been removed; those shared services belong to Pro. The staged transition is documented in [the target architecture](docs/TARGET-ARCHITECTURE.md).

Basic owns contact identity and the WordPress-user-to-person link. Pro owns customer authorization and the shared commercial portal services. Business contributes only explicitly released project, task, correction, file, and approval views. Its WooCommerce My Account endpoint remains a compatibility adapter; no profile, consent, customer, contract, or payment model is duplicated in Business.
