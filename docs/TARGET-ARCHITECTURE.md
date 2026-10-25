# ContactPanda Business target architecture

Business is the project-management branch above Pro. Its annual one-site package price is EUR 249 and includes Pro and Plus Complete commercially.

## Dependency and ownership

The target runtime requires compatible Basic and Pro contracts. Business owns only project-domain data:

- projects, areas, phases, tasks, checklists, comments, files, corrections, and approvals;
- responsibilities, due dates, reminders, time entries, project budgets, capacity, and reports;
- customer-visible project collaboration explicitly released through Pro authorization.

Pro owns customers, customer groups, Support Desk, contracts, subscriptions, recurring payments, portal authorization, common permissions, and the audit service. Business stores references to these records and never creates a parallel customer, contract, subscription, payment, or role model.

## Authorization

Business extends the Pro permission vocabulary for projects, tasks, phases, time, budgets, files, comments, corrections, approvals, imports, and reports. Policy scopes include own, assigned, team, project, customer account, explicitly shared, and all records.

The WordPress administration, customer portal, shared MCP transport, REST API, reports, imports, and future mobile application must call the same Pro policy service. Menu visibility is not sufficient authorization.

## Mobile and synchronization preparation

Business resources must expose stable UUIDs, revisions, timestamps, pagination, and conflict-aware mutations through the family API. File access must be auditable and time-limited. The initial mobile scope includes customer search subject to Pro permissions, projects, tasks, dates/reminders, time entries, comments, files, approvals, and read-only WooCommerce orders exposed by Pro.

## Transition from the 0.8 implementation

1. Keep all existing `cp_business_*` table names and record IDs during the transition.
2. Introduce the Pro authorization contract before changing existing Business guards.
3. Map project participants and existing access levels to Pro assignments and grants additively.
4. Replace direct `manage_options` checks with resource-aware policy checks while retaining administrator compatibility.
5. Stop registering `customer_management`, `contract_management`, `subscription_management`, `payment_management`, and `software_licensing` as Business-owned modules.
6. Replace project-local payment semantics only where they represent reusable customer payment or subscription records. Project budgets and project payment schedules may remain Business records but must link to Pro payment records when collected.
7. Require Pro at runtime after the additive bridge has linked customer-facing project participants.

No migration may rewrite Basic contacts, WooCommerce orders, invoices, external payment data, project IDs, task IDs, comments, files, or import history.

## Implemented foundation

- The plugin header and bootstrap require compatible Basic and Pro versions.
- The license catalog contains only Business and project-management entitlements.
- Administrative, portal, import, MCP, and workspace actions use the shared Pro authorization and audit bridge.
- Customer-facing project participants link additively to Pro customer contexts while Basic remains authoritative for identity data.
- Projects and tasks retain UUIDs, revisions, timestamps, visibility, assignments, and the MCP adapter as foundations for the versioned mobile/API layer.
- Project payment schedules remain Business planning records and can link to Pro invoice and payment records without duplicating the Pro commercial model.

## Remaining stages

- Add time tracking, approval workflows, reminders, and capacity planning to the existing project model.
- Add provider adapters that turn approved project billing milestones into Pro invoice drafts.
- Extend fine-grained assignments beyond the existing read/write/manage project participant levels.
