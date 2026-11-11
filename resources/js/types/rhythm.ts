/**
 * The Rhythm of an expense Category in a month (ADR-0066, ADR-0072); an income
 * Category has none. The only declaration of its values in TypeScript, the
 * counterpart of `App\Enums\Rhythm`.
 */
export type Rhythm = 'fixed_charge' | 'running' | 'even_annual' | 'irregular';
