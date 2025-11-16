# Contributing to FleetManager Pro

First off, thank you for considering contributing to FleetManager Pro! It's people like you that make FleetManager Pro such a great tool.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Process](#development-process)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)

---

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to support@fleetmanager.fr.

### Our Standards

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on what is best for the community
- Show empathy towards other community members

---

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 18+ and npm
- PostgreSQL 16+ (or MySQL 8.0+)
- Redis 7+
- Git

### Development Setup

1. **Fork the repository**

2. **Clone your fork:**
   ```bash
   git clone https://github.com/YOUR_USERNAME/fleetmanager-pro.git
   cd fleetmanager-pro
   ```

3. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

4. **Environment setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database setup:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Start development server:**
   ```bash
   php artisan serve
   php artisan queue:work  # In separate terminal
   ```

7. **Run tests to ensure everything works:**
   ```bash
   php artisan test
   ```

---

## Development Process

### Branching Strategy

We use Git Flow:

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Emergency fixes for production
- `release/*` - Release preparation

### Branch Naming

- Features: `feature/add-vehicle-export`
- Bug fixes: `bugfix/fix-fuel-calculation`
- Hotfixes: `hotfix/security-patch`

### Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, missing semi-colons, etc.)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(vehicles): add bulk import functionality

Implement CSV and Excel import for vehicles with validation
and duplicate detection.

Closes #123
```

```
fix(auth): resolve token expiration issue

Fixed bug where tokens were expiring prematurely due to
incorrect timezone handling.

Fixes #456
```

---

## How to Contribute

### Reporting Bugs

Before creating bug reports, please check existing issues. When creating a bug report, include as many details as possible:

**Bug Report Template:**
```markdown
**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- OS: [e.g., Ubuntu 22.04]
- PHP Version: [e.g., 8.3.1]
- Laravel Version: [e.g., 12.0]
- Browser: [e.g., Chrome 120]

**Additional context**
Add any other context about the problem.
```

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

**Enhancement Template:**
```markdown
**Is your feature request related to a problem?**
A clear description of the problem.

**Describe the solution you'd like**
What you want to happen.

**Describe alternatives you've considered**
Other solutions or features you've considered.

**Additional context**
Any other context or screenshots.
```

### Pull Requests

1. **Create a feature branch:**
   ```bash
   git checkout -b feature/my-new-feature
   ```

2. **Make your changes and commit:**
   ```bash
   git add .
   git commit -m "feat(scope): description"
   ```

3. **Write or update tests**

4. **Ensure all tests pass:**
   ```bash
   php artisan test
   php artisan test --coverage  # Aim for >80% coverage
   ```

5. **Update documentation if needed**

6. **Push to your fork:**
   ```bash
   git push origin feature/my-new-feature
   ```

7. **Create a Pull Request**

---

## Coding Standards

### PHP Code Style

We follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

**Run PHP CS Fixer:**
```bash
composer format
# or
./vendor/bin/php-cs-fixer fix
```

### Laravel Best Practices

- Use Eloquent ORM, avoid raw queries
- Use Form Requests for validation
- Use API Resources for response formatting
- Use Policies for authorization
- Use Jobs for long-running tasks
- Use Events and Listeners for decoupled logic

### Code Organization

```
app/
├── Constants/       # Application constants
├── Helpers/         # Helper functions
├── Http/
│   ├── Controllers/ # Keep controllers thin
│   ├── Requests/    # Form Request classes
│   └── Resources/   # API Resources
├── Jobs/            # Queue jobs
├── Models/          # Eloquent models
├── Notifications/   # Notification classes
├── Policies/        # Authorization policies
└── Services/        # Business logic services
```

### Naming Conventions

- **Controllers:** `VehicleController` (singular, PascalCase)
- **Models:** `Vehicle` (singular, PascalCase)
- **Form Requests:** `StoreVehicleRequest` (PascalCase)
- **Resources:** `VehicleResource` (singular, PascalCase)
- **Jobs:** `SendMaintenanceReminders` (descriptive, PascalCase)
- **Policies:** `VehiclePolicy` (singular, PascalCase)
- **Variables:** `$vehicleCount` (camelCase)
- **Constants:** `VEHICLE_STATUS_ACTIVE` (SCREAMING_SNAKE_CASE)
- **Database tables:** `vehicles` (plural, snake_case)
- **Pivot tables:** `driver_vehicle` (alphabetical, singular, snake_case)

---

## Testing Guidelines

### Test Structure

```php
public function test_user_can_create_vehicle_with_valid_data(): void
{
    // Arrange
    $user = User::factory()->create();
    $data = ['registration_number' => 'AB-123-CD', ...];

    // Act
    $response = $this->actingAs($user)->postJson('/api/vehicles', $data);

    // Assert
    $response->assertStatus(201);
    $this->assertDatabaseHas('vehicles', ['registration_number' => 'AB-123-CD']);
}
```

### Test Types

- **Unit Tests:** Test individual methods and classes
- **Feature Tests:** Test HTTP endpoints and integrations
- **Integration Tests:** Test multiple components working together

### Testing Requirements

- All new features must include tests
- Bug fixes must include regression tests
- Aim for 80%+ code coverage
- Tests must be deterministic (no random data without seeds)
- Use factories for test data
- Clean up test data properly

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/VehiclePolicyTest.php

# Run with coverage
php artisan test --coverage

# Run in parallel (faster)
php artisan test --parallel
```

---

## Pull Request Process

### Before Submitting

- [ ] Code follows PSR-12 standards
- [ ] All tests pass locally
- [ ] New code has tests (>80% coverage)
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated
- [ ] No merge conflicts with `develop`
- [ ] Commits follow conventional commits

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
Describe the tests you ran

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Commented hard-to-understand areas
- [ ] Documentation updated
- [ ] No new warnings
- [ ] Tests added
- [ ] All tests passing
- [ ] CHANGELOG updated

## Screenshots (if applicable)

## Related Issues
Closes #(issue number)
```

### Review Process

1. At least one approval required
2. All CI checks must pass
3. No merge conflicts
4. Reviewer may request changes
5. Once approved, maintainer will merge

---

## API Development

### Creating New Endpoints

1. **Define route** in `routes/api.php`
2. **Create controller method**
3. **Create Form Request** for validation
4. **Create API Resource** for response formatting
5. **Create Policy** for authorization
6. **Write tests** (unit + feature)
7. **Add Scribe annotations** for documentation

Example:
```php
/**
 * Store Vehicle
 *
 * Create a new vehicle in the fleet.
 *
 * @bodyParam registration_number string required Example: AB-123-CD
 * @bodyParam make string required Example: Renault
 * @bodyParam model string required Example: Kangoo
 *
 * @response 201 {"data": {"id": 1, "registration_number": "AB-123-CD"}}
 */
public function store(StoreVehicleRequest $request)
{
    $vehicle = Vehicle::create($request->validated());
    return new VehicleResource($vehicle);
}
```

---

## Documentation

### Code Comments

- Write self-documenting code when possible
- Add comments for complex business logic
- Use PHPDoc for all public methods
- Document magic numbers with constants

### API Documentation

- Use Scribe annotations for all endpoints
- Provide request/response examples
- Document error responses
- Update after every API change

### Regenerate Docs

```bash
php artisan scribe:generate
```

---

## Questions?

Feel free to:
- Open an issue for discussion
- Contact maintainers at support@fleetmanager.fr
- Check existing issues and pull requests

---

## License

By contributing, you agree that your contributions will be licensed under the same license as the project (MIT License).

---

## Recognition

Contributors will be recognized in:
- CHANGELOG.md
- Project README.md
- GitHub contributors page

Thank you for making FleetManager Pro better! 🚀
