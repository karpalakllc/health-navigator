import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  Checkbox,
  Fieldset,
  Input,
  Radio,
  Select,
  Textarea,
} from "@/components/ui/field";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("Input", () => {
  it("is named by its visible label", () => {
    render(<Input label="Град" />);

    expect(screen.getByRole("textbox", { name: "Град" })).toBeInTheDocument();
    expect(screen.getByRole("textbox")).not.toHaveAttribute("aria-invalid");
    expect(screen.getByRole("textbox")).not.toHaveAttribute("aria-describedby");
  });

  it("links the hint and the error, and marks the field invalid", () => {
    render(
      <Input
        label="Е-пошта"
        hint="Само за најава."
        error="Внесете важечка е-адреса."
      />,
    );

    const input = screen.getByRole("textbox", { name: "Е-пошта" });
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAccessibleDescription(
      `Само за најава. ${t("ui.errorPrefix")} Внесете важечка е-адреса.`,
    );
  });

  it("keeps a caller's own aria-describedby and a fixed id", () => {
    render(
      <>
        <p id="extra">Дополнително</p>
        <Input label="Име" id="name" aria-describedby="extra" hint="Совет" />
      </>,
    );

    const input = screen.getByRole("textbox", { name: "Име" });
    expect(input).toHaveAttribute("id", "name");
    expect(input.getAttribute("aria-describedby")?.split(" ")).toEqual([
      "name-hint",
      "extra",
    ]);
  });

  it("announces the required state in the label", () => {
    render(<Input label="Лозинка" required />);

    expect(screen.getByRole("textbox")).toHaveAccessibleName(
      `Лозинка (${t("ui.required")})`,
    );
  });
});

describe("Textarea / Select", () => {
  it("describes the textarea by its hint and counter", () => {
    render(
      <Textarea
        label="Вашиот одговор"
        hint="Без лични податоци."
        counter="0 / 5.000"
      />,
    );

    expect(
      screen.getByRole("textbox", { name: "Вашиот одговор" }),
    ).toHaveAccessibleDescription("Без лични податоци. 0 / 5.000");
  });

  it("labels the select and marks errors", () => {
    render(
      <Select label="Подреди" error="Изберете редослед.">
        <option>Најрелевантни</option>
      </Select>,
    );

    const select = screen.getByRole("combobox", { name: "Подреди" });
    expect(select).toHaveAttribute("aria-invalid", "true");
    expect(select).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} Изберете редослед.`,
    );
  });
});

describe("Checkbox / Radio / Fieldset", () => {
  it("names the checkbox by its row label and describes it by its error", () => {
    render(<Checkbox label="Ги прифаќам правилата" error="Задолжително." />);

    const box = screen.getByRole("checkbox", { name: "Ги прифаќам правилата" });
    expect(box).toHaveAttribute("aria-invalid", "true");
    expect(box).toHaveAccessibleDescription(
      `${t("ui.errorPrefix")} Задолжително.`,
    );
  });

  it("groups radios under a named fieldset with a description", () => {
    render(
      <Fieldset legend="Оцена" hint="Изберете една.">
        <Radio name="r" label="Сите" />
        <Radio name="r" label="4+" />
      </Fieldset>,
    );

    const group = screen.getByRole("group", { name: "Оцена" });
    expect(group).toHaveAccessibleDescription("Изберете една.");
    expect(screen.getAllByRole("radio")).toHaveLength(2);
    expect(screen.getByRole("radio", { name: "4+" })).toBeInTheDocument();
  });

  it("has no serious accessibility violations", async () => {
    const { container } = render(
      <form>
        <Input label="Град" hint="Пр. Скопје" />
        <Input label="Е-пошта" error="Грешна адреса." />
        <Textarea label="Порака" counter="0 / 100" />
        <Select label="Подреди">
          <option>А</option>
        </Select>
        <Fieldset legend="Оцена" error="Изберете оцена.">
          <Radio name="x" label="Сите" />
        </Fieldset>
        <Checkbox label="Се согласувам" />
      </form>,
    );

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
