# SharedKernel/Contract/

Types that cross module boundaries: `IntegrationEvent` (base for every published `<Thing>V1` event: id, name, version, occurredAt), `UserId`, `Currency`. Events are immutable and carry no secrets. Depend on nothing but PHP.
