import { Chip } from '@nextui-org/react';

interface StatusCodeProps {
  status: number;
  size?: 'sm' | 'md' | 'lg';
}

function getStatusColor(status: number): 'success' | 'warning' | 'danger' | 'primary' | 'default' {
  if (status >= 500) return 'danger';
  if (status >= 400) return 'warning';
  if (status >= 300) return 'primary';
  if (status >= 200) return 'success';
  return 'default';
}

export default function StatusCode({ status, size = 'sm' }: StatusCodeProps) {
  return (
    <Chip color={getStatusColor(status)} variant="flat" size={size}>
      {status}
    </Chip>
  );
}
