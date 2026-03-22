import { Badge } from "@/components/ui/badge";

interface StatusCodeProps {
  status: number;
}

function getStatusVariant(
  status: number,
): "success" | "warning" | "danger" | "info" | "default" {
  if (status >= 500) return "danger";
  if (status >= 400) return "warning";
  if (status >= 300) return "info";
  if (status >= 200) return "success";
  return "default";
}

export default function StatusCode({ status }: StatusCodeProps) {
  return <Badge variant={getStatusVariant(status)}>{status}</Badge>;
}
