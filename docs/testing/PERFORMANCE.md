# Performance Benchmarks — Laravel Schema Sentinel v2.1.0

All benchmarks were measured on Arch Linux (CachyOS x86_64, 2-core CPU / 32GB RAM, PHP 8.5.11, Laravel 13.37.0).

## Representative Schema Workloads

### Workload 1: Small Schema (3 Core Tables: Users, Password Reset Tokens, Sessions)
- **Migration Simulation Time**: 58.96 ms
- **Schema Parsing Time**: 4.79 ms
- **Diff Analysis Time**: 0.42 ms
- **Peak Memory Consumption**: 14.00 MB
- **Total Command Execution**: ~70 ms

### Workload 2: Medium Schema (10 Relational & Complex Tables)
- Tables: `users`, `password_reset_tokens`, `cache`, `jobs`, `profiles`, `posts`, `categories`, `comments`, `products`, `orders`, `order_items`, `payments`.
- Foreign Keys: 9 cascading and nullable constraints.
- Indexes: Composite, unique, and UUID primary keys.
- **Migration Simulation Time**: 72.98 ms
- **Schema Parsing Time**: 12.10 ms
- **Diff Analysis Time**: 1.15 ms
- **Peak Memory Consumption**: 16.00 MB
- **Total Command Execution**: ~95 ms

### Workload 3: Snapshot Loading Comparison (10 Tables)
- **Snapshot File Read & Hydration Time**: 0.57 ms (vs 72.98 ms for shadow migration replay)
- **Speedup**: **~128x faster** than full migration simulation.
- **Ideal Use Case**: CI/CD pipelines validating PRs against a pre-compiled migration snapshot.
