import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function Field({
    label,
    error,
    hint,
    required = false,
    className,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <label className={cn('flex flex-col gap-1.5', className)}>
            <span className="text-sm font-medium">
                {label}
                {!required && (
                    <span className="text-muted-foreground font-normal">
                        {' '}
                        (optional)
                    </span>
                )}
            </span>
            {children}
            {error ? (
                <span className="text-destructive text-xs">{error}</span>
            ) : (
                hint && (
                    <span className="text-muted-foreground text-xs">{hint}</span>
                )
            )}
        </label>
    );
}

export function TextField({
    label,
    value,
    onChange,
    error,
    hint,
    required = false,
    type = 'text',
    placeholder,
    className,
    ...rest
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
    required?: boolean;
    type?: string;
    placeholder?: string;
    className?: string;
    min?: string;
    max?: string;
    step?: string;
}) {
    return (
        <Field
            label={label}
            error={error}
            hint={hint}
            required={required}
            className={className}
        >
            <Input
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                {...rest}
            />
        </Field>
    );
}

export function TextArea({
    label,
    value,
    onChange,
    error,
    hint,
    rows = 3,
    placeholder,
    required = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
    rows?: number;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <Field label={label} error={error} hint={hint} required={required}>
            <textarea
                rows={rows}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
            />
        </Field>
    );
}

export function SelectField({
    label,
    value,
    onChange,
    options,
    error,
    required = false,
    placeholder,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    options: { value: string | number; label: string }[];
    error?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <Field label={label} error={error} required={required}>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </Field>
    );
}

export function Toggle({
    label,
    hint,
    checked,
    onChange,
}: {
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-start gap-2.5">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="accent-primary mt-0.5 size-4"
            />
            <span>
                <span className="block text-sm font-medium">{label}</span>
                {hint && (
                    <span className="text-muted-foreground block text-xs">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}

/** A comma-free list editor for things like specialities and plan perks. */
export function ListField({
    label,
    values,
    onChange,
    placeholder,
    hint,
}: {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    placeholder?: string;
    hint?: string;
}) {
    const rows = values.length > 0 ? values : [''];

    return (
        <Field label={label} hint={hint}>
            <div className="space-y-2">
                {rows.map((value, index) => (
                    <div key={index} className="flex gap-2">
                        <Input
                            value={value}
                            placeholder={placeholder}
                            onChange={(event) => {
                                const next = [...rows];
                                next[index] = event.target.value;
                                onChange(next);
                            }}
                        />
                        <button
                            type="button"
                            onClick={() =>
                                onChange(rows.filter((_, i) => i !== index))
                            }
                            className="text-muted-foreground hover:text-destructive px-2 text-sm"
                            aria-label={`Remove ${label} row ${index + 1}`}
                        >
                            Remove
                        </button>
                    </div>
                ))}
                <button
                    type="button"
                    onClick={() => onChange([...rows, ''])}
                    className="text-primary text-sm font-medium hover:underline"
                >
                    Add another
                </button>
            </div>
        </Field>
    );
}

export function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="bg-card rounded-xl border p-5">
            <h2 className="font-semibold">{title}</h2>
            {description && (
                <p className="text-muted-foreground mt-0.5 mb-4 text-sm">
                    {description}
                </p>
            )}
            <div className={cn('space-y-4', !description && 'mt-4')}>
                {children}
            </div>
        </section>
    );
}
