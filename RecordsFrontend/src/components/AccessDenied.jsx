import { Lock } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";

export default function AccessDenied({ message = "You do not have permission to access this page. Contact an administrator.", icon: Icon = Lock }) {
  return (
    <Card className="border-csit-border bg-white">
      <Empty className="py-16">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon />
          </EmptyMedia>
          <EmptyTitle>Access restricted</EmptyTitle>
          <EmptyDescription>{message}</EmptyDescription>
        </EmptyHeader>
      </Empty>
    </Card>
  );
}
