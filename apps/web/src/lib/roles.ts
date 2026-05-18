import { mk } from "@/i18n/mk";

const labels: Record<string, string> = {
  member: mk.account.roleMember,
  moderator: mk.account.roleModerator,
  admin: mk.account.roleAdmin,
};

const communityRoleLabels: Record<string, string> = {
  "Forum Moderator": mk.account.roleForumModerator,
};

export function roleLabel(role: string): string {
  return labels[role] ?? role;
}

export function accountRoleLabel(user: { role: string; community_roles?: string[] }): string {
  const community = user.community_roles ?? [];

  for (const name of community) {
    const label = communityRoleLabels[name];

    if (label) {
      return label;
    }
  }

  return roleLabel(user.role);
}
