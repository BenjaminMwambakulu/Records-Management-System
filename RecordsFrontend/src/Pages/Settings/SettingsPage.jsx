import { useAuth } from "@/Context/AuthContext";
import { api } from "@/APIClients/APIClient";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { LogOut, Loader2, Check, X, Camera, AlertCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";

function getInitials(name) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();
}

export default function SettingsPage() {
  const { user, logout, refreshUser } = useAuth();
  const [editingName, setEditingName] = useState(false);
  const [nameValue, setNameValue] = useState("");
  const [savingName, setSavingName] = useState(false);
  const [nameError, setNameError] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [avatarPreview, setAvatarPreview] = useState(null);
  const [avatarError, setAvatarError] = useState(null);
  const [profile, setProfile] = useState(null);
  const fileInputRef = useRef(null);

  useEffect(() => {
    api.get("/v1/me").then((res) => setProfile(res?.data)).catch(() => {});
  }, []);

  const rawName = user?.name;
  const isPlaceholderName =
    !rawName ||
    /^(undefined|null|NaN|\[object Object\])(\s+(undefined|null|NaN))?$/i.test(
      rawName.trim()
    );
  const displayName = isPlaceholderName ? user?.username || "Guest" : rawName;
  const displayEmail = user?.email || "Not signed in";
  const initials = getInitials(displayName);
  const currentAvatar = avatarPreview || profile?.avatar || user?.avatar;

  const handleLogout = () => {
    logout(import.meta.env.VITE_LOGTO_POST_LOGOUT_REDIRECT_URI);
  };

  const handleAvatarChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setAvatarError(null);
    const preview = URL.createObjectURL(file);
    setAvatarPreview(preview);
    setUploading(true);

    const formData = new FormData();
    formData.append("avatar", file);

    try {
      await api.post("/v1/me/avatar", formData);
      const res = await api.get("/v1/me");
      setProfile(res?.data);
      await refreshUser();
      setAvatarPreview(null);
    } catch (err) {
      const msg = err.body?.message || err.message || "Upload failed";
      setAvatarError(msg);
      setAvatarPreview(null);
    } finally {
      setUploading(false);
    }
  };

  const startEditName = () => {
    setNameValue(displayName);
    setNameError(null);
    setEditingName(true);
  };

  const cancelEditName = () => {
    setEditingName(false);
    setNameValue("");
    setNameError(null);
  };

  const saveName = async () => {
    const trimmed = nameValue.trim();
    if (!trimmed || trimmed === displayName) {
      setEditingName(false);
      return;
    }
    setSavingName(true);
    setNameError(null);
    try {
      await api.patch("/v1/me", { name: trimmed });
      const res = await api.get("/v1/me");
      setProfile(res?.data);
      await refreshUser();
      setEditingName(false);
    } catch (err) {
      const msg = err.body?.message || err.message || "Update failed";
      setNameError(msg);
    } finally {
      setSavingName(false);
    }
  };

  return (
    <div className="max-w-7xl space-y-6 py-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
        <p className="text-sm text-muted-foreground">
          Manage your account and preferences
        </p>
      </div>

      {/* ── Profile ── */}
      <Card>
        <CardHeader>
          <CardTitle>Profile</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-4">
            <div className="relative">
              <Avatar className="size-16">
                {currentAvatar ? (
                  <AvatarImage src={currentAvatar} alt={displayName} />
                ) : null}
                <AvatarFallback className="text-lg">{initials}</AvatarFallback>
              </Avatar>
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading}
                className="absolute -right-1 -bottom-1 flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-50"
              >
                {uploading ? (
                  <Loader2 className="size-3.5 animate-spin" />
                ) : (
                  <Camera className="size-3.5" />
                )}
              </button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={handleAvatarChange}
                className="hidden"
              />
            </div>
            <div className="space-y-1">
              <p className="text-base font-medium">{displayName}</p>
              <p className="text-sm text-muted-foreground">{displayEmail}</p>
              {user?.username && (
                <p className="text-xs text-muted-foreground">
                  @{user.username}
                </p>
              )}
            </div>
          </div>

          {avatarError && (
            <div className="mt-3 flex items-center gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
              <AlertCircle className="size-4 shrink-0" />
              {avatarError}
            </div>
          )}

          <Separator className="my-4" />

          <div className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <p className="font-medium text-foreground">Name</p>
              {editingName ? (
                <div className="mt-1 flex items-center gap-2">
                  <Input
                    value={nameValue}
                    onChange={(e) => setNameValue(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter") saveName();
                      if (e.key === "Escape") cancelEditName();
                    }}
                    autoFocus
                    className="h-8 max-w-xs"
                  />
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={saveName}
                    disabled={savingName}
                  >
                    {savingName ? (
                      <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                      <Check className="size-3.5" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={cancelEditName}
                  >
                    <X className="size-3.5" />
                  </Button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={startEditName}
                  className="group mt-1 flex items-center gap-1 text-muted-foreground hover:text-foreground"
                >
                  {displayName}
                  <span className="text-xs opacity-0 transition-opacity group-hover:opacity-100">
                    Edit
                  </span>
                </button>
              )}
              {nameError && (
                <p className="mt-1 flex items-center gap-1 text-xs text-destructive">
                  <AlertCircle className="size-3" />
                  {nameError}
                </p>
              )}
            </div>
            <div>
              <p className="font-medium text-foreground">Email</p>
              <p className="text-muted-foreground">{displayEmail}</p>
            </div>
            {user?.username && (
              <div>
                <p className="font-medium text-foreground">Username</p>
                <p className="text-muted-foreground">@{user.username}</p>
              </div>
            )}
            {profile?.academic_track_label && (
              <div>
                <p className="font-medium text-foreground">Academic Track</p>
                <p className="text-muted-foreground">{profile.academic_track_label}</p>
              </div>
            )}
            {profile?.enrolled_year && (
              <div>
                <p className="font-medium text-foreground">Enrolled Year</p>
                <p className="text-muted-foreground">{profile.enrolled_year}</p>
              </div>
            )}
            {profile?.study_year && (
              <div>
                <p className="font-medium text-foreground">Study Year</p>
                <p className="text-muted-foreground">{profile.study_year}</p>
              </div>
            )}
            {profile?.skills?.length > 0 && (
              <div className="sm:col-span-2 lg:col-span-3">
                <p className="font-medium text-foreground">Skills</p>
                <div className="mt-1 flex flex-wrap gap-1.5">
                  {profile.skills.map((skill) => (
                    <span
                      key={skill}
                      className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                      {skill}
                    </span>
                  ))}
                </div>
              </div>
            )}
            {profile?.roles?.length > 0 && (
              <div className="sm:col-span-2 lg:col-span-3">
                <p className="font-medium text-foreground">Roles</p>
                <div className="mt-1 flex flex-wrap gap-1.5">
                  {profile.roles.map((role) => (
                    <span
                      key={role}
                      className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                      {role}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* ── Account Actions ── */}
      <Card>
        <CardHeader>
          <CardTitle>Account</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-foreground">Sign out</p>
              <p className="text-xs text-muted-foreground">
                End your current session
              </p>
            </div>
            <Button
              variant="destructive"
              size="sm"
              onClick={handleLogout}
              className="gap-1.5"
            >
              <LogOut className="size-3.5" />
              Sign out
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
