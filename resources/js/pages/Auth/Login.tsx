import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import Guest from '@/Layouts/Guest';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';

export default function Login() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: ''
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const onHandleChange = (event) => {
        setData(event.target.name, event.target.value);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <Guest>
            <Head title={t('auth.login')} />

            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">{t('auth.welcomeBack')}</CardTitle>
                    <CardDescription className="text-sm">{t('auth.loginWithEmail')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <div className="grid gap-6">
                            <div className="grid gap-6">
                                <div className="grid gap-3">
                                    <Label htmlFor="email">{t('auth.email')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="m@example.com"
                                        required
                                        onChange={onHandleChange}
                                        name="email"
                                    />
                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                </div>
                                <div className="grid gap-3">
                                    <Label htmlFor="password">{t('auth.password')}</Label>
                                    <Input id="password" type="password" required onChange={onHandleChange} name="password" />
                                </div>
                                <Button type="submit" disabled={processing} className="w-full">
                                    {t('auth.login')}
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </Guest>
    );
}
