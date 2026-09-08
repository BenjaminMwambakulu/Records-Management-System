import { useCallback, useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import "date-utils";
import { ArrowLeft, Download, FileText, Loader2, Plus } from "lucide-react";
import { api, apiFetch } from "@/APIClients/APIClient";
import { useAuth } from "@/Context/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { isDocumentManager } from "@/lib/roles";
import { extractErrorMessage } from "@/lib/errors";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

function MetaRow({ label, value }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-b border-csit-border py-2 text-sm last:border-0">
      <span className="text-csit-text-muted">{label}</span>
      <span className="font-medium text-csit-text">{value ?? "—"}</span>
    </div>
  );
}

function DocumentPreview({ version }) {
  if (!version?.file_url) {
    return (
      <div className="flex h-full min-h-64 flex-col items-center justify-center gap-2 text-csit-text-muted">
        <FileText className="size-10" />
        <p className="text-sm">No file uploaded for preview</p>
      </div>
    );
  }

  const mime = version.mime_type ?? "";

  if (mime === "application/pdf") {
    return (
      <iframe
        src={version.file_url}
        title={`Preview of version ${version.version_number}`}
        className="h-full w-full rounded-lg border border-csit-border bg-white"
      />
    );
  }

  if (mime.startsWith("image/")) {
    return (
      <div className="flex h-full min-h-64 items-center justify-center rounded-lg border border-csit-border bg-white p-4">
        <img
          src={version.file_url}
          alt={`Preview of version ${version.version_number}`}
          className="max-h-[60vh] w-auto max-w-full object-contain"
        />
      </div>
    );
  }

  return (
    <div className="flex h-full min-h-64 flex-col items-center justify-center gap-3 text-csit-text-muted">
      <FileText className="size-10" />
      <p className="max-w-sm text-center text-sm">
        Preview is not available for this file type. You can download it instead.
      </p>
      <a
        href={version.file_url}
        target="_blank"
        rel="noreferrer"
        className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-csit-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-csit-primary/90"
      >
        <Download />
        Download
      </a>
    </div>
  );
}

export default function DocumentDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const canManage = isDocumentManager(user);

  const [document, setDocument] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  const [versionFile, setVersionFile] = useState(null);
  const [changeSummary, setChangeSummary] = useState("");
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState(null);

  const [isTogglingPublic, setIsTogglingPublic] = useState(false);
  const [publicError, setPublicError] = useState(null);

  const loadDocument = useCallback(() => {
    setIsLoading(true);
    setError(null);
    api
      .get(`/v1/documents/${id}`)
      .then((body) => setDocument(body?.data ?? null))
      .catch((err) => setError(err))
      .finally(() => setIsLoading(false));
  }, [id]);

  useEffect(() => {
    loadDocument();
  }, [loadDocument]);

  const handleUploadVersion = async (event) => {
    event.preventDefault();
    if (!versionFile) return;
    setIsUploading(true);
    setUploadError(null);
    try {
      const formData = new FormData();
      formData.append("file", versionFile);
      if (changeSummary.trim()) formData.append("change_summary", changeSummary.trim());
      await apiFetch(`/v1/documents/${document.id}/versions`, { method: "POST", body: formData });
      setVersionFile(null);
      setChangeSummary("");
      await loadDocument();
    } catch (err) {
      setUploadError(extractErrorMessage(err));
    } finally {
      setIsUploading(false);
    }
  };

  const handleTogglePublic = async (next) => {
    if (!document) return;
    setIsTogglingPublic(true);
    setPublicError(null);
    try {
      const body = await api.put(`/v1/documents/${document.id}`, { is_public: next });
      setDocument((current) => ({
        ...current,
        is_public: body?.data?.is_public ?? next,
      }));
    } catch (err) {
      setPublicError(extractErrorMessage(err));
    } finally {
      setIsTogglingPublic(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex min-h-64 items-center justify-center">
        <Loader2 size={32} className="animate-spin text-[#7A5CF0]" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
        <p className="text-sm text-rose-600">Failed to load this document.</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate("/app/documents")}>
            Back to documents
          </Button>
          <Button onClick={loadDocument}>Retry</Button>
        </div>
      </div>
    );
  }

  if (!document) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
        <p className="text-sm text-csit-text-muted">Document not found.</p>
        <Button variant="outline" onClick={() => navigate("/app/documents")}>
          Back to documents
        </Button>
      </div>
    );
  }

  const latestVersion = document.latest_version;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="inline-flex items-center gap-1.5 text-sm font-medium text-csit-text-muted transition-colors hover:text-csit-text"
        >
          <ArrowLeft className="size-4" />
          Back to documents
        </button>
        {latestVersion?.file_url ? (
          <a
            href={latestVersion.file_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-csit-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-csit-primary/90"
          >
            <Download />
            Download latest
          </a>
        ) : null}
      </div>

      <div className="flex flex-col gap-6 xl:flex-row">
        <Card className="flex-1 border-csit-border bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold text-csit-text">Preview</h2>
          <div className="h-[60vh]">
            <DocumentPreview version={latestVersion} />
          </div>
        </Card>

        <div className="flex w-full flex-col gap-6 xl:w-80">
          <Card className="border-csit-border bg-white p-4">
            <h2 className="mb-3 text-sm font-semibold text-csit-text">Document details</h2>
            <MetaRow label="Title" value={document.title} />
            <MetaRow label="Category" value={document.category?.name ?? "Uncategorized"} />
            <MetaRow label="Status" value={document.status} />
            {canManage ? (
              <div className="flex items-center justify-between gap-4 border-b border-csit-border py-2 text-sm last:border-0">
                <span className="text-csit-text-muted">Public</span>
                <div className="flex items-center gap-2">
                  {isTogglingPublic ? (
                    <Loader2 className="size-4 animate-spin text-csit-text-muted" />
                  ) : null}
                  <Switch
                    checked={Boolean(document.is_public)}
                    onCheckedChange={handleTogglePublic}
                    disabled={isTogglingPublic}
                  />
                </div>
              </div>
            ) : (
              <MetaRow label="Visibility" value={document.is_public ? "Public" : "Private"} />
            )}
            {publicError ? (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                {publicError}
              </div>
            ) : null}
            <MetaRow label="Latest version" value={latestVersion?.version_number ?? "—"} />
            <MetaRow label="Change summary" value={latestVersion?.change_summary ?? "—"} />
            <MetaRow label="Uploaded" value={formatDate(latestVersion?.created_at)} />
            <MetaRow label="Created" value={formatDate(document.created_at)} />
            <MetaRow label="Updated" value={formatDate(document.updated_at)} />
          </Card>

          {canManage ? (
            <Card className="border-csit-border bg-white p-4">
              <h2 className="mb-3 text-sm font-semibold text-csit-text">Upload new version</h2>
              <form onSubmit={handleUploadVersion} className="flex flex-col gap-3">
                <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                  File
                  <Input
                    required
                    type="file"
                    value={undefined}
                    onChange={(event) => setVersionFile(event.target.files?.[0] ?? null)}
                  />
                </label>
                <label className="flex flex-col gap-1.5 text-xs font-medium text-csit-text-muted">
                  Change summary
                  <Input
                    value={changeSummary}
                    onChange={(event) => setChangeSummary(event.target.value)}
                    placeholder="What changed in this version?"
                  />
                </label>
                {uploadError && (
                  <div className="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs text-rose-600">
                    {uploadError}
                  </div>
                )}
                <div className="flex justify-end">
                  <Button type="submit" size="sm" disabled={isUploading || !versionFile}>
                    <Plus />
                    {isUploading ? "Uploading…" : "Upload version"}
                  </Button>
                </div>
              </form>
            </Card>
          ) : null}
        </div>
      </div>

      <Card className="border-csit-border bg-white">
        <div className="border-b border-csit-border px-4 py-3">
          <h2 className="text-sm font-semibold text-csit-text">
            Versions ({document.versions?.length ?? 0})
          </h2>
        </div>
        {!document.versions?.length ? (
          <p className="px-4 py-6 text-sm text-csit-text-muted">No versions uploaded yet.</p>
        ) : (
          <ul className="flex flex-col gap-2 p-4">
            {document.versions.map((version) => (
              <li
                key={version.id}
                className="flex items-center justify-between gap-3 rounded-lg border border-csit-border px-3 py-2 text-sm"
              >
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <FileText className="size-4 shrink-0 text-csit-text-muted" />
                    <span className="font-medium text-csit-text">v{version.version_number}</span>
                    {version.version_number === latestVersion?.version_number ? (
                      <span className="rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700">
                        Latest
                      </span>
                    ) : null}
                  </div>
                  <p className="mt-0.5 truncate text-xs text-csit-text-muted">
                    {version.change_summary || "No change summary"}
                  </p>
                  <p className="text-xs text-csit-text-muted">{formatDate(version.created_at)}</p>
                </div>
                {version.file_url ? (
                  <a
                    href={version.file_url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={`Download version ${version.version_number}`}
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-csit-text-muted transition-colors hover:bg-accent hover:text-foreground"
                  >
                    <Download />
                  </a>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}