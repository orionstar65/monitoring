# Super Admin, delegated Admin and unified monitoring rollout

The web dashboard has three roles:

| Role | Data scope | Account/monitor management | Destructive actions |
|---|---|---|---|
| Super Admin (`super-admin`) | Every user and monitor | Full administration, including role/assignment management | Yes, including log and monitor deletion/merge |
| Admin (`admin`) | Only explicitly assigned regular users and their monitors | View logs/screenshots, edit assigned users' profile data and monitor metadata | No deletion, merge, user creation/role changes, ownership transfers or password resets |
| User (`user`) | Own aggregate summaries | Existing nonprivileged functions | No |

## Before deploying

1. **Back up** the existing `monitoring_db` (schema, data and stored screenshot directories).
2. Take a brief web maintenance window. The old PHP role checks will not recognize the new role until the matching code is deployed. Avoid deploying code and migration separately into a live mixed-version fleet of web PHP workers.
3. If not applied already, confirm the prior `2026_11_05_users_two_roles_remove_email.sql` migration is complete.
4. Apply `Backend/migrations/2026_11_06_super_admin_scoped_admin.sql` to the database, then deploy the matching main-branch PHP/CSS/JS files and restart/reload PHP workers so opcode caches are cleared.
5. Verify **all existing admins now have `role='super-admin'`**:

   ```sql
   SELECT role, COUNT(*) AS accounts FROM users GROUP BY role;
   SHOW TABLES LIKE 'admin_user_assignments';
   ```

   Never run the role update in reverse. Do not edit the role enum by hand after assigning Admin accounts.

This migration **has not been executed on your server** by these commits.

## Assign a delegated Admin

Sign in as a Super Admin. In **User Management**, create a user with role **Admin**, or edit an existing account's role. In **Edit User → Assigned users**, check the regular users they may manage and save. The server checks assignments on every request, including screenshot media and keyboard context endpoints; removing an assignment revokes new requests immediately.

User-management and monitor-management pages list only assigned accounts/devices to that Admin. The Admin cannot grant itself additional access. The Settings tab hides the merge action and keeps ownership read-only.

## Verification before opening access

From the repository root, run locally (not GitHub Actions):

```bash
php -l Backend/html/app/controllers/Base.php
php -l Backend/html/app/controllers/Users.php
php -l Backend/html/app/controllers/Monitors.php
php -l Backend/html/app/controllers/View.php
php -l Backend/html/app/controllers/Logbrowse.php
php -l Backend/html/app/views/users/_account_form.php
php -l Backend/html/app/views/users/edit.php
php -l Backend/html/app/views/view/index.php
php -l Backend/html/app/views/view/logbrowse.php
php Backend/tests/scoped_admin_and_history_toolbar_contract.php
php Backend/tests/unified_monitoring_workspace_contract.php
node Backend/tests/timeline_geometry_test.js
node Backend/tests/monitoring_date_transcript_test.js
```

**Live negative tests are mandatory before production use.** Create two regular users, each with a monitor and keyboard/browser records. Assign only one user to an Admin. Verify that the Admin:

- Sees only assigned monitors in the left list, management tables and live status.
- Can edit assigned user's User No/full name and monitor type, OS, notes and lock. Cannot alter role, password or monitor ownership.
- Receives HTTP 403 for unassigned monitor IDs when requesting screenshots, image files, navigation, timeline, keyboard context, browser logs, USB logs and settings.
- Receives HTTP 403 when invoking delete, merge, user creation and password reset endpoints, even by manually issuing requests.
- Loses access when the Super Admin removes the user assignment without needing to log out.
- Does not receive an all-device keyboard/browser response when no monitor is selected.

Verify Super Admin still sees every monitor and retains the original tools. Verify ordinary User accounts still only see their permitted aggregate views.

## Real-time UI and CCTV

Both the regular Screenshots tab and fullscreen modal now use a shared screenshot tile renderer and the same **30-second refresh cycle**. Entering fullscreen no longer starts another timer. New screenshots still depend on the client upload schedule and the next poll; this is periodic polling, not streaming.

Screenshot cards show an online/idle/offline dot at top-left, a capture timestamp at top-right, and a black 80%-opacity bottom strip with **User No: Full Name** and IP.

Both Keyboard and Screenshot History have one compact action bar containing From, To, Scale, +/- zoom, fit and interval navigation (Keyboard has previous/next interval on the same bar). The timeline is directly below and transcript follows it.

## Rollback

Before deployment, retain a restorable database snapshot and the previous PHP release. Since the previous code expects a different role enum, **restore the database snapshot and previous code together** if you must roll back; reverting only the PHP while keeping `super-admin` roles would lock administrators out. Do not roll back by blindly renaming delegated Admins to the previous unrestricted role.
