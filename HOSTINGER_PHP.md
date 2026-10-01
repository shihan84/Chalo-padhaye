# Hostinger PHP Deployment Branch

> **PERMANENT DEPLOYMENT BRANCH — DO NOT MERGE INTO `main`.**

This branch is reserved for the Hostinger Web Hosting edition of Chalo Padhaye.

## Deployment separation

- `main`: existing Vercel / FastAPI / Supabase application.
- `hostinger-php`: Hostinger Web Hosting / PHP / MySQL application.
- Hostinger must deploy only from `hostinger-php`.
- Changes made specifically for PHP/MySQL must not be merged into `main`.

## Target architecture

Browser → PHP application/API → Hostinger MySQL

External services such as Groq and Fish Audio remain server-side integrations.

## Secrets

Never commit production credentials, passwords, private keys, or API keys.

Deployment/database values must be supplied using Hostinger environment configuration or GitHub Actions secrets as appropriate. Expected secret/configuration categories include:

- database host, port, name, username and password
- Groq API key/model
- Fish Audio API key/voice/model
- SSH deployment host/user/key/port when GitHub Actions SSH deployment is enabled

A committed example configuration may contain variable names and safe defaults only.

## Migration rule

The existing Vercel deployment and Supabase implementation remain operational while this branch is developed and tested. Database migration/cutover must happen only after the Hostinger application passes functional testing.

## Planned implementation

1. Inventory current FastAPI endpoints and frontend dependencies.
2. Define the MySQL schema corresponding to the current Supabase data model.
3. Implement PHP configuration, database connection, authentication and authorization.
4. Port API endpoints while preserving frontend contracts where practical.
5. Adapt the frontend away from direct Supabase dependencies.
6. Add a GitHub Actions deployment workflow for this branch.
7. Test on Hostinger before any production cutover.
