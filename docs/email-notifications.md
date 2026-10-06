# Email notifications (2026-10-02)

The goal: give people a reason to come back. Before this, the only email was the price-drop email for logged-in users who followed products.

## What a subscriber gets

They choose on the settings page (`/pranesimai/{token}`):

| Email | When | Command |
|---|---|---|
| Atpigo sekamos prekės | twice a day, when a followed product gets cheaper (needs an account) | `price-watch:notify` (existing) |
| Savaitės santrauka | Thursday 10:00: new leaflets + the best offers of their stores | `weekly-digest:send` |
| Naujas leidinys | 10:00 and 18:00: leaflets of their stores added since the last email | `leaflets:notify-subscribers` |

The stores come from "Mano parduotuvės" at signup and can be changed on the settings page. With no stores picked, the emails cover the main pharmacy chains (`config('stores.main_slugs')`: Eurovaistinė, Gintarinė, Camelia, Benu, Apotheka).

## How it works

- **Data:** `email_subscribers` (email, `store_slugs`, `wants_weekly`, `wants_new_leaflets`, `token`, `confirmed_at`, `unsubscribed_at`, optional `user_id`) and `email_subscriber_flyers` (leaflets already announced to a subscriber).
- **Signup:** `<x-email-signup>` card on `/`, `/pradzia-beta`, `/leidiniai` and the leaflet viewer's side column (which also adds that leaflet's store). It posts to `POST /pranesimai`.
- **Double opt-in:** nothing is sent until the confirmation link in `WeeklyDigestConfirmMail` is clicked, so nobody can sign up someone else's address.
  - A confirmed address can't be changed through the form; changes go through its own settings link.
  - Signed in with the same verified address: no confirmation step.
- **Logged-in users:** `/pranesimai` (side menu "Pranešimai el. paštu", and a link on `/favorites`) opens their own settings page. It's created on the first visit with the subscriber emails off, so opening it never signs anyone up. The "Atpigo sekamos prekės" switch there toggles `users.price_watch_unsubscribed_at`.
- **Every email links to the settings page:** change stores or emails, or "Atsisakyti visų laiškų".
- **Content:** `App\Services\WeeklyDigestBuilder`. It uses leaflets with `valid_from` from 7 days ago to 3 days ahead, and the `store_top_offers` curated pool, at most 3 per store and 8 in total. It's built once per distinct store set.
- **"Naujas leidinys"** sends only leaflets created after the subscriber confirmed, so a new subscriber doesn't get every current leaflet at once. Each leaflet goes out once per subscriber.
- **GA:** `utm_source=weekly` / `new_leaflet`, `utm_medium=email`.

## Commands

```bash
sail artisan weekly-digest:send --dry-run
sail artisan weekly-digest:send --email=someone@example.com   # one test send, ignores the 6-day guard
sail artisan leaflets:notify-subscribers --dry-run
```

Locally, mail goes to Mailhog (http://localhost:8025).

## Tests

`tests/Feature/EmailSubscriptionTest.php`:
- signup and confirmation;
- a confirmed address can't be overwritten;
- a signed-in user skips confirmation;
- the settings page and unsubscribe;
- `/pranesimai` for a signed-in user;
- the weekly email sent once per week to confirmed subscribers only;
- a new leaflet announced once, and only if added after signup.
