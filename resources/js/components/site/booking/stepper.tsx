import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StepDefinition = {
    key: string;
    label: string;
};

/**
 * Shows where you are and lets you jump back to anything already answered.
 * Steps ahead of the furthest point reached stay unreachable, so the wizard
 * can never be skipped into an invalid state.
 */
export function Stepper({
    steps,
    current,
    furthest,
    onJump,
}: {
    steps: StepDefinition[];
    current: number;
    furthest: number;
    onJump: (index: number) => void;
}) {
    return (
        <nav aria-label="Booking steps">
            <ol className="flex items-center gap-2 sm:gap-3">
                {steps.map((step, index) => {
                    const done = index < furthest;
                    const active = index === current;
                    const reachable = index <= furthest;

                    return (
                        <li key={step.key} className="flex flex-1 items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                onClick={() => reachable && onJump(index)}
                                disabled={!reachable}
                                aria-current={active ? 'step' : undefined}
                                className={cn(
                                    'flex min-w-0 flex-1 flex-col gap-1.5 text-left transition-opacity',
                                    !reachable && 'cursor-not-allowed opacity-40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'h-1 w-full rounded-full transition-colors duration-300',
                                        active
                                            ? 'bg-gold'
                                            : done
                                              ? 'bg-gold/50'
                                              : 'bg-bone/15',
                                    )}
                                />
                                <span
                                    className={cn(
                                        'flex items-center gap-1.5 truncate text-xs font-medium transition-colors',
                                        active
                                            ? 'text-gold'
                                            : done
                                              ? 'text-bone/70'
                                              : 'text-smoke',
                                    )}
                                >
                                    {done ? (
                                        <Check
                                            className="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <span className="tabular-nums">
                                            {index + 1}.
                                        </span>
                                    )}
                                    <span className="truncate">{step.label}</span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
