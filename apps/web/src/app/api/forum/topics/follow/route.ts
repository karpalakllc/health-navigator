import { forwardTopicAction } from "@/lib/forum/topic-proxy";

/** „Следи ја темата“: PUT follows, DELETE stops following. */
export async function PUT(request: Request) {
  return forwardTopicAction(request, "PUT", "follow");
}

export async function DELETE(request: Request) {
  return forwardTopicAction(request, "DELETE", "follow");
}
