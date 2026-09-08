import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { formatMoney } from "@/lib/utils";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleDateString();
}

function typeBadgeClass(type) {
  return type === "income"
    ? "bg-emerald-50 text-emerald-700"
    : "bg-rose-50 text-rose-700";
}

export default function FinancialRecordDetailDialog({
  open,
  onOpenChange,
  record,
}) {
  if (!record) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Record details</DialogTitle>
          <DialogDescription>
            Viewing {record.title}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-3 text-sm">
          <div className="flex flex-col gap-1">
            <span className="text-xs font-medium text-csit-text-muted">Title</span>
            <span className="text-csit-text">{record.title}</span>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="flex flex-col gap-1">
              <span className="text-xs font-medium text-csit-text-muted">Type</span>
              <span className={`inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${typeBadgeClass(record.type)}`}>
                {record.type === "income" ? "Income" : "Expense"}
              </span>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-xs font-medium text-csit-text-muted">Amount</span>
              <span className="text-csit-text font-medium">{formatMoney(record.amount)}</span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="flex flex-col gap-1">
              <span className="text-xs font-medium text-csit-text-muted">Category</span>
              <span className="text-csit-text">{record.category?.name ?? "Uncategorized"}</span>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-xs font-medium text-csit-text-muted">Transaction date</span>
              <span className="text-csit-text">{formatDate(record.transaction_date)}</span>
            </div>
          </div>

          {record.recorded_by && (
            <div className="flex flex-col gap-1">
              <span className="text-xs font-medium text-csit-text-muted">Recorded by</span>
              <span className="text-csit-text">{record.recorded_by.name}</span>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
