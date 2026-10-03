# Mail/

`SymfonyAuthEmailSender` sends verification and reset emails through Symfony Mailer using `translations/auth_mail+*`. Integration tests read mails from Mailpit.

The links in the emails point at the web app: `WEB_APP_URL` (one origin per environment, set in `.env` for local) plus the fixed paths `/verify-email?token=` and `/reset-password?token=`, configured in `config/services/authentication.yaml`. The web app calls `POST /auth/email/verify` and `POST /auth/password/reset` with the token.
