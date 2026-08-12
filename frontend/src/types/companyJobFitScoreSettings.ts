export type FitScoreWeightKey =
  | 'required_skills'
  | 'preferred_skills'
  | 'experience'
  | 'work_type'
  | 'location'
  | 'salary';

export type FitScoreWeights = Record<FitScoreWeightKey, number>;

export type FitScoreWeightSource = 'default' | 'custom';

export interface CompanyJobFitScoreSettings {
  weights: FitScoreWeights;
  source: FitScoreWeightSource;
}

export interface UpdateCompanyJobFitScoreSettingsPayload {
  weights: FitScoreWeights;
}

export const FIT_SCORE_WEIGHT_KEYS: FitScoreWeightKey[] = [
  'required_skills',
  'preferred_skills',
  'experience',
  'work_type',
  'location',
  'salary',
];

export const DEFAULT_FIT_SCORE_WEIGHTS: FitScoreWeights = {
  required_skills: 35,
  preferred_skills: 15,
  experience: 20,
  work_type: 15,
  location: 10,
  salary: 5,
};

export const FIT_SCORE_WEIGHT_SIGNALS: Array<{
  key: FitScoreWeightKey;
  label: string;
  description: string;
}> = [
  {
    key: 'required_skills',
    label: 'Gerekli Yetenekler',
    description: 'İlanda zorunlu olarak belirtilen yeteneklerin adayda bulunması',
  },
  {
    key: 'preferred_skills',
    label: 'Tercih Edilen Yetenekler',
    description: 'İlanda tercih edilen yeteneklerin adayda bulunması',
  },
  {
    key: 'experience',
    label: 'Deneyim',
    description: 'Adayın deneyim seviyesi ve işin gerektirdiği deneyim seviyesi',
  },
  {
    key: 'work_type',
    label: 'Çalışma Şekli',
    description: 'Adayın çalışma tercihi ile ilanın çalışma şeklinin uyumu',
  },
  {
    key: 'location',
    label: 'Lokasyon',
    description: 'Adayın lokasyonu ile iş ilanının lokasyonunun uyumu',
  },
  {
    key: 'salary',
    label: 'Maaş',
    description: 'Adayın maaş beklentisi ile ilanın maaş aralığının uyumu',
  },
];
