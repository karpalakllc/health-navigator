import { forwardTopicAction } from "@/lib/forum/topic-proxy";

/** „Најкорисен одговор“: PUT marks a reply ({ postId }), DELETE clears it. */
export async function PUT(request: Request) {
  return forwardTopicAction(request, "PUT", "accepted-answer");
}

export async function DELETE(request: Request) {
  return forwardTopicAction(request, "DELETE", "accepted-answer");
}
