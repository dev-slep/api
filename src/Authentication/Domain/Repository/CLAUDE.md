# Domain/Repository/

Persistence interfaces per aggregate. Each has a Doctrine and an in-memory implementation in `Infrastructure/Persistence`, both verified by the same repository contract tests. Add a method here, then implement it in both.
