from django.core.management.base import BaseCommand

from apps.accounts.models import Role, User


class Command(BaseCommand):
    help = 'Seed default roles (author, editor, super_admin) and assign super_admin to the admin user.'

    ROLES = [
        {
            'name': 'author',
            'level': 1,
            'permissions': [],
        },
        {
            'name': 'editor',
            'level': 2,
            'permissions': [
                'articles.*',
                'comments.*',
                'media.*',
                'categories.*',
            ],
        },
        {
            'name': 'super_admin',
            'level': 3,
            'permissions': ['*'],
        },
    ]

    def handle(self, *args, **options):
        for role_data in self.ROLES:
            role, created = Role.objects.get_or_create(
                name=role_data['name'],
                defaults={
                    'level': role_data['level'],
                    'permissions': role_data['permissions'],
                },
            )
            if created:
                self.stdout.write(self.style.SUCCESS(f'Created role: {role.name} (level={role.level})'))
            else:
                self.stdout.write(f'Role already exists: {role.name} (level={role.level})')

        # Assign super_admin role to the admin user
        super_admin_role = Role.objects.filter(name='super_admin').first()
        if super_admin_role:
            try:
                admin_user = User.objects.get(username='admin')
                if admin_user.role != super_admin_role:
                    admin_user.role = super_admin_role
                    admin_user.save(update_fields=['role'])
                    self.stdout.write(self.style.SUCCESS(
                        f'Assigned super_admin role to user "{admin_user.username}".'
                    ))
                else:
                    self.stdout.write(f'User "admin" already has super_admin role.')
            except User.DoesNotExist:
                self.stdout.write(self.style.WARNING('User "admin" not found — skipping role assignment.'))
