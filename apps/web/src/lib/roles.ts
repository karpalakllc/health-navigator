import { mk } from "@/i18n/mk";

const labels: Record<string, string> = {
  member: mk.account.roleMember,
  moderator: mk.account.roleModerator,
  admin: mk.account.roleAdmin,
};

export function roleLabel(role: string): string {
  return labels[role] ?? role;
}
