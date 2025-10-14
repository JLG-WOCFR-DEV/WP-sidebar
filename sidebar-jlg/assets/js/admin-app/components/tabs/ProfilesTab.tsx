import { Card, CardBody, CardHeader, Notice } from '@wordpress/components';

import { useOptionsStore } from '../../store/optionsStore';

const ProfilesTab = (): JSX.Element => {
  const profiles = useOptionsStore((state) => state.profiles);
  const activeProfile = useOptionsStore((state) => state.activeProfile);

  if (!profiles.length) {
    return (
      <Notice status="warning" isDismissible={false}>
        Aucun profil personnalisé n&apos;est encore configuré. Utilisez le canvas pour en créer un en quelques clics.
      </Notice>
    );
  }

  return (
    <div className="sidebar-jlg-tab sidebar-jlg-tab--profiles">
      {profiles.map((profile: unknown) => {
        const data = profile as Record<string, unknown>;
        const id = (data.id as string) ?? 'default';
        const title = (data.title as string) ?? id;
        const description = (data.description as string) ?? '';
        const enabled = data.enabled !== false;

        return (
          <Card key={id} className="sidebar-jlg-profile-card">
            <CardHeader>
              <span className="sidebar-jlg-profile-card__title">{title}</span>
              {id === activeProfile && <span className="sidebar-jlg-profile-card__badge">Actif</span>}
            </CardHeader>
            <CardBody>
              <p className="sidebar-jlg-profile-card__description">{description || 'Profil sans description.'}</p>
              <p className="sidebar-jlg-profile-card__meta">
                Statut : {enabled ? 'Activé' : 'Désactivé'}
              </p>
            </CardBody>
          </Card>
        );
      })}
    </div>
  );
};

export default ProfilesTab;
