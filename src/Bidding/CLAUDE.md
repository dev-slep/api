# src/Bidding/

Bids (price + ETA) on tow requests until the deadline, the choice window, accepting one bid.

Consumes TowRequest events; publishes `BidPlacedV1`, `BidAcceptedV1`, `BidsClosedV1`.

Status: scaffolded, not implemented yet (empty layer folders with `.gitkeep`).

Layout and dependency rules are in `../CLAUDE.md`. When implementing:
1. Domain first (model, repository interface, exceptions implementing `ProblemType`), then Application (commands, handlers, ports), then Infrastructure (Doctrine records and XML mapping, controllers, adapters). Keep `Contract/` to what other modules truly need.
2. Own PostgreSQL schema per module: create it with `make migration m=Bidding`.
3. Wire services in `config/services/bidding.yaml`; the controller path is already imported in `config/routes/modules.yaml`.
4. Tests mirror `src/` under `tests/Unit|Application|Integration/Bidding/` (see `../../tests/CLAUDE.md`). The authentication module is the reference implementation.
5. Run `make qa` before finishing.
