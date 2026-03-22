import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Skeleton,
  Divider,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@nextui-org/react';
import { useJobDetail } from '@/api/jobs';
import { useRetryJob, useDeleteJob } from '@/api/mutations';
import JobStatusBadge from '@/components/JobStatusBadge';
import PayloadViewer from '@/components/PayloadViewer';
import ExceptionTrace from '@/components/ExceptionTrace';

function formatMs(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return '--';
  if (ms >= 1000) return `${(ms / 1000).toFixed(2)}s`;
  return `${ms.toFixed(0)}ms`;
}

function formatTime(iso: string | null | undefined): string {
  if (!iso) return '--';
  return new Date(iso).toLocaleString();
}

export default function JobDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: job, isLoading, error } = useJobDetail(id);
  const retryJob = useRetryJob();
  const deleteJob = useDeleteJob();
  const deleteModal = useDisclosure();

  const isFailed = job?.status === 'failed';

  const handleRetry = () => {
    if (id) retryJob.mutate(id);
  };

  const confirmDelete = () => {
    if (id) {
      deleteJob.mutate(id, {
        onSuccess: () => navigate('/jobs/failed'),
      });
    }
    deleteModal.onClose();
  };

  const copyToClipboard = async (text: string) => {
    await navigator.clipboard.writeText(text);
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="w-48 h-8 rounded-lg" />
        <Skeleton className="w-full h-40 rounded-lg" />
        <Skeleton className="w-full h-60 rounded-lg" />
      </div>
    );
  }

  if (error || !job) {
    return (
      <div className="flex flex-col items-center justify-center py-20 gap-4">
        <p className="text-danger text-sm">
          {error ? 'Failed to load job details' : 'Job not found'}
        </p>
        <Button
          size="sm"
          variant="flat"
          onPress={() => navigate(-1)}
        >
          Go Back
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="space-y-2">
          <div className="flex items-center gap-3">
            <Button
              size="sm"
              variant="flat"
              onPress={() => navigate(-1)}
            >
              Back
            </Button>
            <h2 className="text-xl font-semibold font-mono">{job.class}</h2>
          </div>
          <div className="flex items-center gap-2">
            <JobStatusBadge status={job.status} />
            <Chip size="sm" variant="bordered">{job.queue}</Chip>
          </div>
        </div>
        {isFailed && (
          <div className="flex gap-2">
            <Button
              size="sm"
              color="primary"
              onPress={handleRetry}
              isLoading={retryJob.isPending}
            >
              Retry
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              onPress={deleteModal.onOpen}
            >
              Delete
            </Button>
          </div>
        )}
      </div>

      {/* Metadata */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-sm font-semibold">Job Details</h3>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
              <p className="text-default-500 text-xs">Job ID</p>
              <div className="flex items-center gap-1">
                <p className="font-mono text-xs truncate">{job.id}</p>
                <Button
                  size="sm"
                  variant="light"
                  isIconOnly
                  onPress={() => void copyToClipboard(job.id)}
                  className="min-w-0 w-6 h-6"
                  aria-label="Copy job ID"
                >
                  <span className="text-xs">C</span>
                </Button>
              </div>
            </div>
            {job.connection && (
              <div>
                <p className="text-default-500 text-xs">Connection</p>
                <p>{job.connection}</p>
              </div>
            )}
            <div>
              <p className="text-default-500 text-xs">Attempts</p>
              <p>
                {job.attempts}
                {job.max_attempts !== undefined && ` / ${job.max_attempts}`}
              </p>
            </div>
            {job.timeout !== undefined && (
              <div>
                <p className="text-default-500 text-xs">Timeout</p>
                <p>{job.timeout}s</p>
              </div>
            )}
            <div>
              <p className="text-default-500 text-xs">Runtime</p>
              <p className="font-mono">{formatMs(job.runtime_ms)}</p>
            </div>
            <div>
              <p className="text-default-500 text-xs">Created</p>
              <p className="text-xs">{formatTime(job.created_at)}</p>
            </div>
            {job.completed_at && (
              <div>
                <p className="text-default-500 text-xs">Completed</p>
                <p className="text-xs">{formatTime(job.completed_at)}</p>
              </div>
            )}
            {job.failed_at && (
              <div>
                <p className="text-default-500 text-xs">Failed</p>
                <p className="text-xs text-danger">{formatTime(job.failed_at)}</p>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Payload */}
      <Card shadow="sm">
        <CardHeader className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">Payload</h3>
          {job.payload && (
            <Button
              size="sm"
              variant="flat"
              onPress={() => void copyToClipboard(JSON.stringify(job.payload, null, 2))}
            >
              Copy
            </Button>
          )}
        </CardHeader>
        <CardBody>
          <PayloadViewer payload={job.payload} />
        </CardBody>
      </Card>

      {/* Exception (failed jobs only) */}
      {isFailed && (job.exception_class || job.exception_message) && (
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-sm font-semibold text-danger">Exception</h3>
          </CardHeader>
          <CardBody>
            <ExceptionTrace
              exceptionClass={job.exception_class}
              exceptionMessage={job.exception_message}
              trace={job.exception_trace}
            />
          </CardBody>
        </Card>
      )}

      {/* Retry success feedback */}
      {retryJob.isSuccess && (
        <>
          <Divider />
          <p className="text-success text-sm">
            Job has been re-enqueued for processing.
          </p>
        </>
      )}

      {/* Delete confirmation modal */}
      <Modal isOpen={deleteModal.isOpen} onOpenChange={deleteModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Delete Failed Job</ModalHeader>
              <ModalBody>
                <p>
                  Are you sure you want to permanently delete this failed job?
                  This action cannot be undone.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button color="danger" onPress={confirmDelete}>
                  Delete
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
