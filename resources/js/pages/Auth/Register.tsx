import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import Guest from '@/Layouts/Guest';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const onHandleChange = (event) => {
        setData(event.target.name, event.target.value);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('register'));
    };

    return (
        <Guest>
            <Head title={t('auth.register')} />

            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">{t('auth.createAccount')}</CardTitle>
                    <CardDescription className="text-sm">{t('auth.registerWithEmail')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <div className="grid gap-6">
                            <div className="grid gap-3">
                                <Label htmlFor="name">{t('auth.name')}</Label>
                                <Input id="name" name="name" required onChange={onHandleChange} value={data.name} />
                                {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                            </div>
                            <div className="grid gap-3">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input id="email" type="email" name="email" required onChange={onHandleChange} value={data.email} />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>
                            <div className="grid gap-3">
                                <Label htmlFor="password">{t('auth.password')}</Label>
                                <Input id="password" type="password" name="password" required onChange={onHandleChange} value={data.password} />
                                {errors.password && <p className="text-destructive text-sm">{errors.password}</p>}
                            </div>
                            <div className="grid gap-3">
                                <Label htmlFor="password_confirmation">{t('auth.confirmPassword')}</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    onChange={onHandleChange}
                                    value={data.password_confirmation}
                                />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                {t('auth.register')}
                            </Button>
                            <p className="text-center text-sm text-muted-foreground">
                                {t('auth.hasAccount')}{' '}
                                <Link href={route('login')} className="font-medium text-foreground underline underline-offset-4">
                                    {t('auth.login')}
                                </Link>
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </Guest>
    );
}