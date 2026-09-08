export { cn } from "cn"

export function getImagePath(ImageName) {
  return new URL(`../assets/images/${ImageName}`, import.meta.url).href
}

export function formatMoney(value) {
  const amount = Number(value);
  if (Number.isNaN(amount)) return "—";
  return `K ${amount.toLocaleString("en-ZM", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

