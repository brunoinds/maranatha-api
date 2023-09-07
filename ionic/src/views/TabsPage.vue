<template>
  <ion-page>
    <ion-tabs>
      <ion-router-outlet></ion-router-outlet>
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="tab1" href="/reports">
          <ion-icon aria-hidden="true" :icon="albumsOutline" />
          <ion-label>Mis Reportes</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="tab2" href="/account">
          <ion-icon aria-hidden="true" :icon="personCircleOutline" />
          <ion-label>Mi cuenta</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  </ion-page>
</template>

<script setup lang="ts">
import { IonTabBar, IonTabButton, IonTabs, IonLabel, IonIcon, IonPage, IonRouterOutlet } from '@ionic/vue';
import { albumsOutline, personCircleOutline } from 'ionicons/icons';
</script>
