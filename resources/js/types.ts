export type Status = 'planned' | 'in_transit' | 'arrived' | 'in_storage' | 'delivered';

export const STATUSES: { value: Status; label: string }[] = [
    { value: 'planned', label: 'Planned' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'arrived', label: 'Arrived' },
    { value: 'in_storage', label: 'In storage' },
    { value: 'delivered', label: 'Delivered' },
];

export interface User {
    id: number;
    name: string;
    email: string;
    company: { id: number; name: string; slug: string };
}

export interface ShipmentRef {
    id: number;
    reference: string;
    status: Status;
    status_label: string;
    etd: string | null;
    eta: string | null;
    ata: string | null;
    destination_port: string;
}

export interface ShipmentSummary {
    id: number;
    reference: string;
    status: Status;
    status_label: string;
    allowed_transitions: Status[];
    shipping_method: string;
    shipping_line: string | null;
    origin_port: string | null;
    destination_port: string;
    incoterm: string | null;
    etd: string;
    eta: string;
    ata: string | null;
    days_delayed: number;
    is_delayed: boolean;
    status_changed_at: string | null;
    notes: string | null;
    containers_count: number;
    container_nos: string[];
    cargo_items_count: number;
    delivered_items_count: number;
    customers: string[];
    proforma_invoice_nos: string[];
}

export interface CargoItem {
    id: number;
    container_id: number;
    container_no?: string;
    shipment?: ShipmentRef;
    customer?: { id: number; name: string };
    product?: { id: number; material_code: string | null; description: string; net_type: string; net_type_label: string };
    proforma_invoice_no: string;
    exporter_ref: string | null;
    customer_po: string | null;
    tag_no: string | null;
    package_no: string | null;
    quantity: number;
    unit: string;
    net_weight_kg: number | null;
    gross_weight_kg: number | null;
    is_stock: boolean;
    certificate_sent: boolean;
    status: Status;
    status_label: string;
    customer_delivery_date: string | null;
    delivered_at: string | null;
    days_delayed: number | null;
    comments: string | null;
}

export interface Container {
    id: number;
    shipment_id: number;
    container_no: string;
    seal_no: string | null;
    container_type: string | null;
    customs_cleared: boolean;
    storage_facility: string | null;
    storage_date: string | null;
    pickup_date: string | null;
    cargo_items?: CargoItem[];
    shipment?: ShipmentRef;
}

export interface StatusEntry {
    id: number;
    type: 'status';
    from: Status | null;
    from_label: string | null;
    to: Status;
    to_label: string;
    note: string | null;
    source: string;
    actor: string;
    occurred_at: string;
}

export interface DateEntry {
    id: number;
    type: 'date';
    subject_type: 'shipment' | 'cargo_item';
    subject_id: number;
    subject_label: string | null;
    field: string;
    field_label: string;
    old: string | null;
    new: string | null;
    days_shifted: number | null;
    reason: string;
    source: string;
    actor: string;
    occurred_at: string;
}

export type HistoryEntry = StatusEntry | DateEntry;

export interface Shipment extends ShipmentSummary {
    containers: Container[];
    status_history: StatusEntry[];
    date_changes: DateEntry[];
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number; per_page: number; from: number | null; to: number | null };
}

export interface ImportIssue {
    id: number;
    row_number: number;
    level: 'error' | 'warning';
    column: string | null;
    message: string;
    raw: Record<string, string | null> | null;
}

export interface ImportRun {
    id: number;
    filename: string;
    status: 'running' | 'completed' | 'failed';
    rows_total: number;
    rows_imported: number;
    rows_skipped: number;
    warnings_count: number;
    shipments_created: number;
    containers_created: number;
    cargo_items_created: number;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    user?: string | null;
    issues?: ImportIssue[];
}

export interface LookupResult {
    query: string;
    type: string;
    shipments: ShipmentSummary[];
    containers: Container[];
    cargo_items: CargoItem[];
}

export interface UpcomingReport {
    from: string;
    to: string;
    totals: { deliveries: number; arrivals: number };
    deliveries: CargoItem[];
    arrivals: ShipmentSummary[];
}

export interface OverviewReport {
    as_of: string;
    statuses: { value: Status; label: string }[];
    by_status: Record<'shipments' | 'containers' | 'cargo_items', Record<Status, number>>;
    by_customer: ({ customer: string; cargo_items: number } & Record<Status, number>)[];
    highlights: Record<string, number>;
}

export interface DelayedReport {
    as_of: string;
    totals: { shipments: number; cargo_items: number };
    shipments: ShipmentSummary[];
    cargo_items: CargoItem[];
}
