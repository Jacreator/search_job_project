# Sponsor Edit Permission

Decide who may edit sponsors in a team. Data model, permission, and the toggle only. The dashboard itself is spec 12.

## Rule

- Owners and admins can always edit sponsors.
- Members can edit only when their `can_edit_sponsors` flag is on.
- Owners and admins can turn the flag on or off for any member of their team.
- The flag defaults to off. Members without it can still view the dashboard.

## Migration

`add_can_edit_sponsors_to_team_members_table`: `can_edit_sponsors` (boolean, default false).

## Permission

- Add `TeamPermission::ManageSponsorEditors` (`sponsor:manage-editors`) to the starter kit enum.
- Grant it to Owner (already gets all cases) and Admin in `TeamRole::permissions()`.
- Add `Membership` cast for `can_edit_sponsors` (bool) and add it to the fillable list.
- Add `User::canEditSponsors(Team $team): bool` - true for owner or admin, else the membership flag.

## Toggle

- Route: `PATCH settings/teams/{team}/members/{user}/sponsor-access`, inside the existing `EnsureTeamMembership` group in `routes/settings.php`.
- Form Request authorises with `TeamPermission::ManageSponsorEditors` and validates `can_edit_sponsors` as boolean.
- The target must be a Member of the team. Owners and admins are not toggled (they always can edit).
- UI: on `resources/js/pages/teams/edit.tsx`, show a switch or checkbox "Can edit sponsors" next to each Member, visible only to users with the permission. Read-only label for everyone else.

## Choices Made When Built

- The route is named `teams.members.sponsor-access` and handled by `App\Http\Controllers\Teams\MemberSponsorAccessController` with `UpdateMemberSponsorAccessRequest`.
- The UI uses the existing `Checkbox` (no new shadcn primitive). Users without the permission see "Can edit sponsors" or "Cannot edit sponsors" as plain text. Nothing is shown next to owners and admins.
- Toggling an owner or admin fails validation on `can_edit_sponsors` ("Owners and admins can always edit sponsors."). A target who is not in the team gives 404.
- `canManageSponsorEditors` is added to the `TeamPermissions` data object, and `can_edit_sponsors` to each member on the team edit page.
- Changing a member's role to one that does not need the flag (admin) switches the flag off, in the starter kit's `TeamMemberController::update` (approved by James). A member promoted to admin and later demoted starts with editing off.
- The `canEditSponsors()` tests need the database, so they live in `tests/Feature/Sponsors/SponsorEditPermissionTest.php`.

## Check When Done

- Migration runs and rolls back.
- Owner and admin can toggle a member on and off.
- A member cannot toggle anyone (403).
- Toggling an owner or admin is rejected.
- A user from another team cannot toggle (403).
- `canEditSponsors()` unit tests: owner, admin, member on, member off, non-member.
- Existing starter kit team tests still pass.
