-- ============================================================
-- Agrolytix Supabase Mirror Schema — Phase 1
--
-- INSTRUCTIONS:
--   1. In your Supabase project, open the SQL Editor
--   2. If re-running: DROP all tables first (see bottom)
--   3. Paste this entire file and click Run
--
-- Security: RLS is enabled on every table with no public
-- policies. Only the service_role key (Laravel backend) can
-- read or write mirror data.
--
-- Sensitive fields excluded from mirror:
--   users.password, users.remember_token
-- ============================================================


-- -------------------------
-- businesses
-- -------------------------
CREATE TABLE IF NOT EXISTS businesses (
    id                          bigint PRIMARY KEY,
    parent_id                   bigint,
    name                        text NOT NULL,
    email                       text,
    phone                       text,
    address                     text,
    trial_ends_at               timestamptz,
    subscription_status         text NOT NULL,
    subscription_plan           text,
    total_revenue               numeric(12,2) NOT NULL DEFAULT 0,
    last_payment_date           timestamptz,
    last_payment_status         text,
    paystack_customer_code      text,
    paystack_subscription_code  text,
    paystack_email_token        text,
    subscription_ends_at        timestamptz,
    created_at                  timestamptz,
    updated_at                  timestamptz,
    mirrored_at                 timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE businesses ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- users (password & remember_token excluded)
-- -------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  bigint PRIMARY KEY,
    name                text NOT NULL,
    email               text NOT NULL,
    role                text NOT NULL,
    permissions         text,
    status              text NOT NULL,
    contact             text,
    last_login_at       timestamptz,
    email_verified_at   timestamptz,
    business_id         bigint NOT NULL,
    created_at          timestamptz,
    updated_at          timestamptz,
    mirrored_at         timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- clients
-- -------------------------
CREATE TABLE IF NOT EXISTS clients (
    id           bigint PRIMARY KEY,
    name         text NOT NULL,
    contact      text,
    location     text,
    email        text,
    total_debt   numeric(12,2) NOT NULL DEFAULT 0,
    business_id  bigint NOT NULL,
    created_at   timestamptz,
    updated_at   timestamptz,
    mirrored_at  timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- products
-- -------------------------
CREATE TABLE IF NOT EXISTS products (
    id               bigint PRIMARY KEY,
    name             text NOT NULL,
    category         text,
    description      text,
    cost_price       numeric(12,2) NOT NULL DEFAULT 0,
    sell_price       numeric(12,2) NOT NULL DEFAULT 0,
    quantity         integer NOT NULL DEFAULT 0,
    last_added_qty   integer NOT NULL DEFAULT 0,
    base_unit        text NOT NULL,
    low_stock_alert  integer NOT NULL DEFAULT 10,
    business_id      bigint NOT NULL,
    created_at       timestamptz,
    updated_at       timestamptz,
    mirrored_at      timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE products ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- product_units
-- -------------------------
CREATE TABLE IF NOT EXISTS product_units (
    id                  bigint PRIMARY KEY,
    product_id          bigint NOT NULL,
    unit_name           text NOT NULL,
    quantity_in_base    integer NOT NULL DEFAULT 1,
    price               numeric(12,2) NOT NULL DEFAULT 0,
    is_bulk             boolean NOT NULL DEFAULT false,
    bulk_discount_pct   numeric(5,2) NOT NULL DEFAULT 0,
    business_id         bigint NOT NULL,
    created_at          timestamptz,
    updated_at          timestamptz,
    mirrored_at         timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE product_units ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- wholesale_products
-- -------------------------
CREATE TABLE IF NOT EXISTS wholesale_products (
    id               bigint PRIMARY KEY,
    name             text NOT NULL,
    category         text,
    description      text,
    cost_price       numeric(12,2) NOT NULL DEFAULT 0,
    sell_price       numeric(12,2) NOT NULL DEFAULT 0,
    quantity         integer NOT NULL DEFAULT 0,
    last_added_qty   integer NOT NULL DEFAULT 0,
    base_unit        text NOT NULL,
    low_stock_alert  integer NOT NULL DEFAULT 10,
    business_id      bigint NOT NULL,
    created_at       timestamptz,
    updated_at       timestamptz,
    mirrored_at      timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE wholesale_products ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- wholesale_product_units
-- -------------------------
CREATE TABLE IF NOT EXISTS wholesale_product_units (
    id                    bigint PRIMARY KEY,
    wholesale_product_id  bigint NOT NULL,
    unit_name             text NOT NULL,
    quantity_in_base      integer NOT NULL DEFAULT 1,
    price                 numeric(12,2) NOT NULL DEFAULT 0,
    is_bulk               boolean NOT NULL DEFAULT false,
    bulk_discount_pct     numeric(5,2) NOT NULL DEFAULT 0,
    business_id           bigint NOT NULL,
    created_at            timestamptz,
    updated_at            timestamptz,
    mirrored_at           timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE wholesale_product_units ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- retail_sales
-- -------------------------
CREATE TABLE IF NOT EXISTS retail_sales (
    id              bigint PRIMARY KEY,
    user_id         bigint NOT NULL,
    receipt_number  text NOT NULL,
    total_amount    numeric(12,2) NOT NULL,
    total_cost      numeric(12,2) NOT NULL DEFAULT 0,
    profit          numeric(12,2) NOT NULL DEFAULT 0,
    payment_method  text NOT NULL,
    momo_number     text,
    status          text NOT NULL,
    business_id     bigint NOT NULL,
    created_at      timestamptz,
    updated_at      timestamptz,
    mirrored_at     timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE retail_sales ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- retail_sale_items
-- -------------------------
CREATE TABLE IF NOT EXISTS retail_sale_items (
    id              bigint PRIMARY KEY,
    retail_sale_id  bigint NOT NULL,
    product_id      bigint NOT NULL,
    product_name    text NOT NULL,
    unit_name       text NOT NULL,
    quantity        integer NOT NULL,
    quantity_base   integer NOT NULL DEFAULT 0,
    unit_price      numeric(12,2) NOT NULL,
    cost_price      numeric(12,2) NOT NULL DEFAULT 0,
    subtotal        numeric(12,2) NOT NULL,
    business_id     bigint NOT NULL,
    created_at      timestamptz,
    updated_at      timestamptz,
    mirrored_at     timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE retail_sale_items ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- wholesale_sales
-- -------------------------
CREATE TABLE IF NOT EXISTS wholesale_sales (
    id              bigint PRIMARY KEY,
    user_id         bigint NOT NULL,
    client_id       bigint NOT NULL,
    receipt_number  text NOT NULL,
    total_amount    numeric(12,2) NOT NULL,
    total_cost      numeric(12,2) NOT NULL DEFAULT 0,
    profit          numeric(12,2) NOT NULL DEFAULT 0,
    payment_method  text NOT NULL,
    momo_number     text,
    amount_paid     numeric(12,2) NOT NULL DEFAULT 0,
    debt            numeric(12,2) NOT NULL DEFAULT 0,
    status          text NOT NULL,
    business_id     bigint NOT NULL,
    created_at      timestamptz,
    updated_at      timestamptz,
    mirrored_at     timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE wholesale_sales ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- wholesale_sale_items
-- -------------------------
CREATE TABLE IF NOT EXISTS wholesale_sale_items (
    id                    bigint PRIMARY KEY,
    wholesale_sale_id     bigint NOT NULL,
    wholesale_product_id  bigint NOT NULL,
    product_name          text NOT NULL,
    unit_name             text NOT NULL,
    quantity              integer NOT NULL,
    quantity_base         integer NOT NULL DEFAULT 0,
    unit_price            numeric(12,2) NOT NULL,
    cost_price            numeric(12,2) NOT NULL DEFAULT 0,
    subtotal              numeric(12,2) NOT NULL,
    business_id           bigint NOT NULL,
    created_at            timestamptz,
    updated_at            timestamptz,
    mirrored_at           timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE wholesale_sale_items ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- expenses
-- -------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id            bigint PRIMARY KEY,
    title         text NOT NULL,
    amount        numeric(12,2) NOT NULL,
    category      text NOT NULL,
    note          text,
    expense_date  date NOT NULL,
    recorded_by   bigint NOT NULL,
    business_id   bigint NOT NULL,
    created_at    timestamptz,
    updated_at    timestamptz,
    mirrored_at   timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE expenses ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- reversals
-- -------------------------
CREATE TABLE IF NOT EXISTS reversals (
    id               bigint PRIMARY KEY,
    retail_sale_id   bigint NOT NULL,
    user_id          bigint NOT NULL,
    reason           text,
    reversed_items   text,
    amount_reversed  numeric(12,2) NOT NULL DEFAULT 0,
    cost_reversed    numeric(12,2) NOT NULL DEFAULT 0,
    is_partial       boolean NOT NULL DEFAULT false,
    business_id      bigint NOT NULL,
    created_at       timestamptz,
    updated_at       timestamptz,
    mirrored_at      timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE reversals ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- debts
-- -------------------------
CREATE TABLE IF NOT EXISTS debts (
    id                 bigint PRIMARY KEY,
    wholesale_sale_id  bigint NOT NULL,
    client_id          bigint NOT NULL,
    amount_paid        numeric(12,2) NOT NULL,
    old_debt           numeric(12,2) NOT NULL,
    new_debt           numeric(12,2) NOT NULL,
    note               text,
    business_id        bigint NOT NULL,
    created_at         timestamptz,
    updated_at         timestamptz,
    mirrored_at        timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE debts ENABLE ROW LEVEL SECURITY;

-- -------------------------
-- stock_transfers
-- -------------------------
CREATE TABLE IF NOT EXISTS stock_transfers (
    id                          bigint PRIMARY KEY,
    business_id                 bigint NOT NULL,
    from_type                   text NOT NULL,
    from_product_id             bigint NOT NULL,
    from_product_name           text NOT NULL,
    source_unit_id              bigint,
    source_unit_name            text,
    source_unit_quantity_in_base integer NOT NULL DEFAULT 1,
    source_base_unit            text,
    to_type                     text NOT NULL,
    to_product_id               bigint NOT NULL,
    to_product_name             text NOT NULL,
    to_business_id              bigint,
    auto_created                boolean NOT NULL DEFAULT false,
    display_quantity            integer,
    quantity                    integer NOT NULL,
    note                        text,
    transferred_by              bigint NOT NULL,
    created_at                  timestamptz,
    updated_at                  timestamptz,
    mirrored_at                 timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE stock_transfers ENABLE ROW LEVEL SECURITY;


-- ============================================================
-- To DROP all mirror tables and start fresh, run this first:
-- ============================================================
-- DROP TABLE IF EXISTS stock_transfers, debts, reversals,
--   expenses, wholesale_sale_items, wholesale_sales,
--   retail_sale_items, retail_sales, wholesale_product_units,
--   wholesale_products, product_units, products,
--   clients, users, businesses;
