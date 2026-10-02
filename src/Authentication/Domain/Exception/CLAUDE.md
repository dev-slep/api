# Domain/Exception/

`AuthenticationProblem` (named constructors such as `invalidCredentials()`, `emailNotVerified()`; each has a problem slug and HTTP status) and `InvalidValue` (value-object validation). A new refusal needs a constructor here and a translation in `translations/problems+intl-icu.*.yaml`.
