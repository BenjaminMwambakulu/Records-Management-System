import { useEffect, useState } from "react";
import "date-utils";
import { FileText, FolderOpen, Loader2, Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { apiFetch } from "@/APIClients/APIClient";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

function fileTypeLabel(mimeType) {
  if (!mimeType) return "File";
  if (mimeType.includes("pdf")) return "PDF";
  if (mimeType.includes("image")) return "Image";
  if (mimeType.includes("sheet") || mimeType.includes("csv")) return "Spreadsheet";
  if (mimeType.includes("word") || mimeType === "text/plain") return "Document";
  return mimeType.split("/")[1]?.toUpperCase() || "File";
}

export default function PublicDocuments() {
  const [documents, setDocuments] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);

    apiFetch("/v1/documents?per_page=6")
      .then((body) => {
        if (cancelled) return;
        const list = Array.isArray(body) ? body : Array.isArray(body?.data) ? body.data : [];
        setDocuments(list);
      })
      .catch((err) => {
        if (!cancelled) setError(err);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Loader2 size={28} className="animate-spin text-csit-primary" />
      </div>
    );
  }

  if (error || documents.length === 0) return null;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {documents.map((document) => {
        const latestVersion = document.latest_version;
        const fileUrl = latestVersion?.file_url ?? null;
        const mimeType = latestVersion?.mime_type ?? null;
        return (
          <div
            key={document.id}
            className="group rounded-2xl border border-csit-border/60 bg-white overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col"
          >
            <div className="h-44 flex flex-col items-center justify-center gap-3 bg-csit-surface relative">
              <FileText className="size-12 text-csit-text-muted/40" />
              {fileUrl && (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-white border border-csit-border/60 px-2.5 py-1 text-xs font-medium text-csit-text">
                  {fileTypeLabel(mimeType)}
                </span>
              )}
            </div>
            <div className="p-5 flex flex-col flex-1">
              <div className="flex items-center gap-2 mb-2">
                {document.category ? (
                  <span className="rounded-md bg-[#110b79]/10 px-1.5 py-0.5 text-xs font-medium text-[#110b79]">
                    {document.category.name}
                  </span>
                ) : (
                  <span className="rounded-md bg-csit-surface px-1.5 py-0.5 text-xs font-medium text-csit-text-muted">
                    Uncategorized
                  </span>
                )}
              </div>
              <h3 className="text-lg font-semibold text-csit-text mb-2 group-hover:text-csit-primary transition-colors line-clamp-2">
                {document.title}
              </h3>
              <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-csit-text-muted mb-4">
                <span className="inline-flex items-center gap-1.5">
                  <FolderOpen className="size-3.5" />
                  v{latestVersion?.version_number ?? "—"}
                </span>
                <span>{formatDate(document.created_at)}</span>
              </div>
              {fileUrl ? (
                <a href={fileUrl} target="_blank" rel="noopener noreferrer" className="mt-auto">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 px-3 text-xs font-medium text-csit-primary hover:bg-csit-primary/5 rounded-full gap-1 group/btn"
                  >
                    Open document
                    <Download className="size-3 group-hover/btn:translate-y-0.5 transition-transform" />
                  </Button>
                </a>
              ) : (
                <div className="mt-auto" />
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}