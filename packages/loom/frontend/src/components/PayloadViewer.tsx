import { Accordion, AccordionItem } from '@nextui-org/react';

interface PayloadViewerProps {
  payload: unknown;
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function formatJson(data: unknown): string {
  try {
    return JSON.stringify(data, null, 2);
  } catch {
    return String(data);
  }
}

export default function PayloadViewer({ payload }: PayloadViewerProps) {
  if (payload === null || payload === undefined) {
    return (
      <div className="text-sm text-default-400 italic">No payload data</div>
    );
  }

  const formatted = formatJson(payload);
  const isLarge = formatted.length > 2000;

  const viewer = (
    <pre className="bg-content2 rounded-lg p-4 text-xs font-mono overflow-x-auto trace-scroll whitespace-pre-wrap break-all">
      {formatted}
    </pre>
  );

  if (isLarge && isObject(payload)) {
    return (
      <Accordion variant="bordered">
        {Object.entries(payload).map(([key, value]) => (
          <AccordionItem key={key} title={key} classNames={{ title: 'text-sm font-mono' }}>
            <pre className="bg-content2 rounded-lg p-3 text-xs font-mono overflow-x-auto trace-scroll whitespace-pre-wrap break-all">
              {formatJson(value)}
            </pre>
          </AccordionItem>
        ))}
      </Accordion>
    );
  }

  return viewer;
}
