import { describe, expect, it } from "vitest";
import { mk } from "@/i18n/mk";

describe("no generic disclaimer copy", () => {
  it("has no standing „not a diagnosis“ or „informational“ trust-band strings", () => {
    const flat = JSON.stringify(mk);
    expect(flat).not.toContain("Важно — не е дијагноза");
    expect(flat).not.toContain("Содржината е информативна и не го заменува");
    expect(flat).not.toContain("не дијагноза, не итна служба");
  });
});
