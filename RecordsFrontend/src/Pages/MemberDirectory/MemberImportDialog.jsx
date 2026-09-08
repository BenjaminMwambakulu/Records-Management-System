import { useEffect, useRef, useState } from "react";
import { Download, Upload } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { apiFetch } from "@/APIClients/APIClient";
import { api } from "@/APIClients/APIClient";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { extractErrorMessage } from "./MemberFormDialog";

const DONE_STATUSES = ["completed", "failed"];

function isDone(status) {
  return DONE_STATUSES.includes(status);
}

export default function MemberImportDialog({
  open,
  onOpenChange,
  onImported,
}) {
  const [file, setFile] = useState(null);
  const [activeImport, setActiveImport] = useState(null);
  const [processingId, setProcessingId] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const onImportedRef = useRef(onImported);

  useEffect(() => {
    onImportedRef.current = onImported;
  });

  useEffect(() => {
    if (!processingId) return;
    let cancelled = false;

    const poll = async () => {
      try {
        const response = await api.get(`/v1/members/imports/${processingId}`);
        if (cancelled) return;
        setActiveImport(response.data);
        if (isDone(response.data.status)) {
          setProcessingId(null);
          if (response.data.status === "completed") {
            onImportedRef.current?.();
          }
        }
      } catch (err) {
        if (!cancelled) {
          setError(extractErrorMessage(err));
          setProcessingId(null);
        }
      }
    };

    poll();
    const timer = setInterval(poll, 2500);

    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [processingId]);

  const reset = () => {
    setFile(null);
    setActiveImport(null);
    setProcessingId(null);
    setError(null);
  };

  const handleClose = () => {
    if (processingId) return;
    reset();
    onOpenChange(false);
  };

  const handleDownloadTemplate = async () => {
    setError(null);
    try {
      const response = await apiFetch("/v1/members/imports/template", {
        raw: true,
      });
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "member-import-template.csv";
      link.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (!file) {
      setError("Please choose a CSV or Excel file to import.");
      return;
    }

    setIsSubmitting(true);
    setError(null);
    try {
      const formData = new FormData();
      formData.append("file", file);
      const response = await apiFetch("/v1/members/import", {
        method: "POST",
        body: formData,
      });
      setActiveImport(response.data);
      setProcessingId(response.data.id);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  const importing = processingId !== null && activeImport !== null && !isDone(activeImport.status);

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Import members</DialogTitle>
          <DialogDescription>
            Upload a CSV or Excel file to add members in bulk. Each row is
            validated and new members get the default{" "}
            <span className="font-medium text-foreground">member</span> role.
          </DialogDescription>
        </DialogHeader>

        {!activeImport ? (
          <form id="member-import-form" onSubmit={handleSubmit} className="flex flex-col gap-4">
            <p className="text-xs leading-relaxed text-csit-text-muted">
              Expected columns:{" "}
              <span className="font-medium text-csit-text">
                student_id, first_name, last_name, email
              </span>{" "}
              (required);{" "}
              <span className="font-medium text-csit-text">
                academic_track
              </span>{" "}
              (BIT or CSS),{" "}
              <span className="font-medium text-csit-text">
                enrolled_year
              </span>{" "}
              and{" "}
              <span className="font-medium text-csit-text">study_year</span>{" "}
              (optional).
            </p>

            <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
              File
              <Input
                required
                type="file"
                accept=".csv,.xlsx,.xls"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              />
            </label>

            <Button
              type="button"
              variant="outline"
              size="sm"
              className="w-fit"
              onClick={handleDownloadTemplate}
            >
              <Download />
              Download template
            </Button>

            <p className="text-xs text-csit-text-muted">
              Duplicate emails or student IDs are skipped automatically. Rows
              that fail validation are reported after the import finishes.
            </p>

            {error && (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                {error}
              </div>
            )}
          </form>
        ) : (
          <div className="flex flex-col gap-3">
            {importing ? (
              <div className="flex items-center gap-2 text-sm text-csit-text-muted">
                <Upload
                  size={16}
                  className="animate-bounce text-csit-primary"
                />
                Importing members…
                {activeImport.total_rows
                  ? `${activeImport.processed ?? 0} of ${activeImport.total_rows} rows`
                  : ""}
              </div>
            ) : (
              <>
                <div className="flex flex-wrap gap-2">
                  <span className="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-600">
                    {activeImport.created_count ?? 0} added
                  </span>
                  <span className="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-600">
                    {activeImport.duplicate_count ?? 0} skipped (duplicates)
                  </span>
                  <span className="rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-600">
                    {activeImport.failed_count ?? 0} failed
                  </span>
                </div>

                {activeImport.status === "failed" && (
                  <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                    Import failed: {activeImport.error_message ?? "Unknown error"}
                  </div>
                )}

                {activeImport.error_rows?.length > 0 && (
                  <div className="max-h-48 overflow-y-auto rounded-lg border border-csit-border p-3">
                    <p className="mb-2 text-xs font-semibold text-csit-text">
                      Rows that need attention
                    </p>
                    <ul className="flex flex-col gap-1.5">
                      {activeImport.error_rows.map((entry, index) => (
                        <li
                          key={index}
                          className="text-xs text-csit-text-muted"
                        >
                          Row {entry.row}
                          {entry.student_id ? ` · ${entry.student_id}` : ""} —{" "}
                          {entry.reason ?? "unknown error"}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </>
            )}
          </div>
        )}

        <DialogFooter>
          {!activeImport ? (
            <>
              <Button type="button" variant="outline" onClick={handleClose}>
                Cancel
              </Button>
              <Button type="submit" form="member-import-form" disabled={isSubmitting}>
                {isSubmitting ? "Uploading…" : "Upload & import"}
              </Button>
            </>
          ) : (
            <Button type="button" onClick={handleClose} disabled={importing}>
              {importing ? "Importing…" : "Done"}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}