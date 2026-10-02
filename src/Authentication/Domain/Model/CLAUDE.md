# Domain/Model/

Aggregates and value objects. Aggregates guard their own invariants and record domain events (`UserAccount`: register, verify email, change password, link social identity, ban; `RefreshToken`: issue, rotate, revoke, reuse detection). Value objects validate on construction and throw `InvalidValue`. No Doctrine, no framework.
