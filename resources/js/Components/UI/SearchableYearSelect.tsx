import { useState, useRef, useEffect } from 'react';

interface SearchableYearSelectProps {
  id: string;
  label: string;
  value: string | number;
  onChange: (year: string) => void;
  required?: boolean;
  minYear?: number;
  maxYear?: number;
  error?: string;
  placeholder?: string;
}

export default function SearchableYearSelect({
  id,
  label,
  value,
  onChange,
  required = false,
  minYear = 1975,
  maxYear = new Date().getFullYear() + 6,
  error,
  placeholder = 'Search or select year...',
}: SearchableYearSelectProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Generate array of years descending
  const years = Array.from(
    { length: maxYear - minYear + 1 },
    (_, i) => maxYear - i
  );

  const filteredYears = years.filter((year) =>
    year.toString().includes(searchTerm.trim())
  );

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="space-y-2 relative" ref={dropdownRef}>
      <label htmlFor={id} className="block text-sm font-medium text-foreground">
        {label} {required && <span className="text-destructive">*</span>}
      </label>

      <div className="relative">
        <input
          id={id}
          type="text"
          value={isOpen ? searchTerm : value ? value.toString() : ''}
          placeholder={placeholder}
          onFocus={() => {
            setIsOpen(true);
            setSearchTerm('');
          }}
          onChange={(e) => {
            setSearchTerm(e.target.value);
            if (!isOpen) setIsOpen(true);
            // If user types a valid 4-digit year directly, update value
            if (/^\d{4}$/.test(e.target.value)) {
              onChange(e.target.value);
            }
          }}
          required={required}
          aria-invalid={!!error}
          aria-describedby={error ? `${id}-error` : undefined}
          className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 min-h-touch pr-8"
        />

        <div className="absolute right-3 top-3 pointer-events-none text-muted-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </div>
      </div>

      {isOpen && (
        <div className="absolute z-50 mt-1 w-full max-h-56 overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md p-1 space-y-0.5">
          {filteredYears.length === 0 ? (
            <div className="px-3 py-2 text-xs text-muted-foreground text-center">
              No matching year found
            </div>
          ) : (
            filteredYears.map((year) => {
              const isSelected = value?.toString() === year.toString();
              return (
                <button
                  key={year}
                  type="button"
                  onClick={() => {
                    onChange(year.toString());
                    setIsOpen(false);
                    setSearchTerm('');
                  }}
                  className={`w-full text-left px-3 py-2 text-sm rounded-sm transition-colors flex items-center justify-between ${
                    isSelected
                      ? 'bg-primary text-primary-foreground font-semibold'
                      : 'hover:bg-accent hover:text-accent-foreground'
                  }`}
                >
                  <span>{year}</span>
                  {isSelected && (
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  )}
                </button>
              );
            })
          )}
        </div>
      )}

      {error && (
        <p id={`${id}-error`} className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
