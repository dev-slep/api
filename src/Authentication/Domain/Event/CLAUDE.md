# Domain/Event/

Internal domain events recorded by aggregates (`UserRegistered`, `EmailVerified`, `PasswordChanged`, `TwoFactorEnabled`). They are mapped to public `*V1` integration events by `Application/Service/AuthenticationEventMapper`; never expose them to other modules.
