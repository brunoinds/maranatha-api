<template>
  <ion-page>
    <ion-tabs>
      <ion-router-outlet></ion-router-outlet>
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="tab1" href="/my-reports">
          <ion-icon aria-hidden="true" :icon="fileTrayFullOutline" />
          <ion-label>Mis Reportes</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="tab2" href="/all-reports" v-if="isAdmin">
          <ion-icon aria-hidden="true" :icon="albumsOutline" />
          <ion-label>Reportes</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="tab3" href="/account">
          <ion-icon aria-hidden="true" :icon="personCircleOutline" />
          <ion-label>Mi cuenta</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  </ion-page>
</template>

<script setup lang="ts">
import { Session } from '@/utils/Session/Session';
import { IonTabBar, IonTabButton, IonTabs, IonLabel, IonIcon, IonPage, IonRouterOutlet } from '@ionic/vue';
import { albumsOutline, fileTrayFullOutline, personCircleOutline } from 'ionicons/icons';
import { computed, ref } from 'vue';


const isAdmin = ref(false);

const isAdminCheck = async () => {
    const currentSession = await Session.getCurrentSession();
    if (!currentSession){
      return;
    };

    isAdmin.value = currentSession.roles().includes("admin");
}
isAdminCheck();
</script>
