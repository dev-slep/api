# Scheduler/

`ScheduledTaskProvider` is the interface a module implements to register a recurring task (see `Authentication/Infrastructure/Scheduler/PurgeExpiredTokensTask`); `DefaultSchedule` collects all providers into the Symfony Scheduler.
