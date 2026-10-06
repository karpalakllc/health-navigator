import { revokeTokens } from "./revoke";

/** Signs out every device except this one. */
export async function DELETE(request: Request) {
  return revokeTokens(request, "/me/tokens");
}
