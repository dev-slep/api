# config/services/

One file per module (`authentication.yaml`, `tower.yaml`, ...) plus `shared_kernel.yaml`. They hold what autowiring cannot decide: which implementation binds to a port, `#[Target]`-less aliases, handler tags, module settings and env bindings.

Keep a module's wiring in its own file. Do not reach into another module's services; depend on its Contract interface.
