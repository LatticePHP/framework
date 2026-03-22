export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar_url?: string;
}

export interface Contact {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  company_id?: number;
  company?: Company;
  title?: string;
  status: 'lead' | 'prospect' | 'customer' | 'churned';
  source?: string;
  owner_id: number;
  owner?: User;
  tags?: string[];
  created_at: string;
}

export interface Company {
  id: number;
  name: string;
  domain?: string;
  industry?: string;
  size?: string;
  phone?: string;
  website?: string;
  annual_revenue?: number;
  owner_id: number;
  created_at: string;
}

export interface Deal {
  id: number;
  title: string;
  value: number;
  currency: string;
  stage: 'lead' | 'qualified' | 'proposal' | 'negotiation' | 'closed_won' | 'closed_lost';
  probability: number;
  contact_id?: number;
  contact?: Contact;
  company_id?: number;
  company?: Company;
  owner_id: number;
  expected_close_date?: string;
  created_at: string;
}

export interface Activity {
  id: number;
  type: 'task' | 'call' | 'meeting' | 'email';
  title: string;
  description?: string;
  due_date: string;
  completed_at?: string;
  contact_id?: number;
  contact?: Contact;
  deal_id?: number;
  deal?: Deal;
  owner_id: number;
  priority: 'low' | 'medium' | 'high';
}

export interface Note {
  id: number;
  body: string;
  notable_type: string;
  notable_id: number;
  author_id: number;
  author?: User;
  created_at: string;
}

export interface DashboardStats {
  total_contacts: number;
  active_deals: number;
  pipeline_value: number;
  conversion_rate: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

export interface AuthResponse {
  access_token: string;
  refresh_token?: string;
  user: User;
}

export type DealStage = Deal['stage'];
export type ContactStatus = Contact['status'];
export type ActivityType = Activity['type'];
export type ActivityPriority = Activity['priority'];
